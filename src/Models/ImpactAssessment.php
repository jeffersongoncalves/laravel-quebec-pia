<?php

namespace JeffersonGoncalves\QuebecPia\Models;

use Carbon\CarbonInterval;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use JeffersonGoncalves\QuebecGovernance\Models\ProcessingActivity;
use JeffersonGoncalves\QuebecPia\Enums\AssessmentStatus;
use JeffersonGoncalves\QuebecPia\Enums\AssessmentType;
use JeffersonGoncalves\QuebecPia\Enums\RiskLevel;
use JeffersonGoncalves\QuebecPia\Enums\Threat;
use LogicException;

/**
 * Privacy impact assessment (évaluation des facteurs relatifs à la vie privée, EFVP), proportionate to the
 * sensitivity, purpose, quantity, distribution and medium of the information (ss. 3.3 and 17).
 *
 * @property string $title
 * @property AssessmentType $type
 * @property int|null $processing_activity_id
 * @property string|null $description the processing and the data it collects
 * @property string|null $necessity necessity and proportionality
 * @property array<array-key, array{title: string, threat?: string|null, likelihood: int|string, impact: int|string, measures?: string|null, residual_likelihood?: int|string|null, residual_impact?: int|string|null}>|null $risks
 * @property string|null $person_in_charge_opinion consulted from the outset of the project (s. 3.3)
 * @property string|null $destination outside Quebec: the province or country (s. 17)
 * @property string|null $legal_framework outside Quebec: the legal framework of the destination, and its privacy principles (s. 17)
 * @property bool|null $adequate_protection outside Quebec: the information would receive adequate protection (s. 17)
 * @property bool|null $portability project: the system can communicate computerized information in a structured, commonly used format (s. 3.3)
 * @property AssessmentStatus $status
 * @property Carbon|null $approved_at
 * @property string|null $approved_by
 * @property Carbon|null $next_review_at
 * @property-read ProcessingActivity|null $processingActivity
 */
class ImpactAssessment extends Model
{
    /** Changing any of these after approval sends the assessment back to draft. */
    public const CONTENT = ['title', 'type', 'processing_activity_id', 'description', 'necessity', 'risks', 'person_in_charge_opinion', 'destination', 'legal_framework', 'adequate_protection', 'portability'];

    protected $guarded = [];

    protected $attributes = [
        'status' => 'draft',
        'type' => 'project',
    ];

    public function getTable(): string
    {
        return config('quebec-pia.table', 'quebec_impact_assessments');
    }

    protected function casts(): array
    {
        return [
            'risks' => 'array',
            'type' => AssessmentType::class,
            'adequate_protection' => 'boolean',
            'portability' => 'boolean',
            'status' => AssessmentStatus::class,
            'approved_at' => 'datetime',
            'next_review_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (ImpactAssessment $assessment): void {
            if ($assessment->getOriginal('status') === AssessmentStatus::Approved && $assessment->isDirty(self::CONTENT)) {
                $assessment->forceFill(['status' => AssessmentStatus::Draft, 'approved_at' => null, 'approved_by' => null, 'next_review_at' => null]);
            }
        });
    }

    /**
     * @return BelongsTo<ProcessingActivity, $this>
     */
    public function processingActivity(): BelongsTo
    {
        return $this->belongsTo(ProcessingActivity::class);
    }

    /**
     * Risks with their inherent level and the residual level after the measures
     * (the inherent one when no residual score was given).
     *
     * @return list<array{title: string, threat: Threat|null, likelihood: int, impact: int, level: RiskLevel, measures: string|null, residual_likelihood: int, residual_impact: int, residual_level: RiskLevel}>
     */
    public function scoredRisks(): array
    {
        return array_map(function (array $risk): array {
            $likelihood = (int) $risk['likelihood'];
            $impact = (int) $risk['impact'];
            $residualLikelihood = (int) ($risk['residual_likelihood'] ?? $likelihood);
            $residualImpact = (int) ($risk['residual_impact'] ?? $impact);

            return [
                'title' => $risk['title'],
                'threat' => isset($risk['threat']) ? Threat::tryFrom($risk['threat']) : null,
                'likelihood' => $likelihood,
                'impact' => $impact,
                'level' => RiskLevel::score($likelihood, $impact),
                'measures' => $risk['measures'] ?? null,
                'residual_likelihood' => $residualLikelihood,
                'residual_impact' => $residualImpact,
                'residual_level' => RiskLevel::score($residualLikelihood, $residualImpact),
            ];
        }, array_values($this->risks ?? []));
    }

    public function highestRisk(bool $residual = true): ?RiskLevel
    {
        $highest = null;

        foreach ($this->scoredRisks() as $risk) {
            $level = $residual ? $risk['residual_level'] : $risk['level'];

            if ($highest === null || $level->weight() > $highest->weight()) {
                $highest = $level;
            }
        }

        return $highest;
    }

    /**
     * Outside Quebec: communicate only once approved, with adequate protection (s. 17).
     */
    public function allowsCommunication(): bool
    {
        return $this->type !== AssessmentType::OutsideQuebec
            || ($this->status === AssessmentStatus::Approved && $this->adequate_protection === true);
    }

    public function approve(string $by): static
    {
        if ($this->scoredRisks() === []) {
            throw new LogicException("Assess at least one risk before approving [{$this->title}].");
        }

        if ($this->type === AssessmentType::OutsideQuebec && (blank($this->destination) || $this->adequate_protection === null)) {
            throw new LogicException("Name the destination and conclude on adequate protection before approving [{$this->title}] (s. 17).");
        }

        $this->forceFill([
            'status' => AssessmentStatus::Approved,
            'approved_at' => now(),
            'approved_by' => $by,
            'next_review_at' => now()->add(CarbonInterval::make((string) config('quebec-pia.review_every', '1 year'))),
        ])->save();

        return $this;
    }

    public function isReviewDue(): bool
    {
        return $this->status === AssessmentStatus::Approved && $this->next_review_at?->isPast() === true;
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeDueForReview(Builder $query): void
    {
        $query->where('status', AssessmentStatus::Approved->value)->where('next_review_at', '<', now());
    }
}
