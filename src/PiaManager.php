<?php

namespace JeffersonGoncalves\QuebecPia;

use JeffersonGoncalves\Quebec\Enums\DataCategory;
use JeffersonGoncalves\Quebec\Quebec;
use JeffersonGoncalves\QuebecGovernance\Models\ProcessingActivity;
use JeffersonGoncalves\QuebecPia\Enums\AssessmentType;
use JeffersonGoncalves\QuebecPia\Models\ImpactAssessment;

class PiaManager
{
    public function __construct(protected Quebec $quebec) {}

    /**
     * Processing activities that likely need an assessment and have none of the right type: sensitive
     * information (s. 12) or profiling (s. 8.1) need a project assessment; information communicated
     * outside Quebec, declared on the activity or on a column, needs one before it goes (s. 17).
     *
     * @return list<array{activity: ProcessingActivity, reasons: list<string>}>
     */
    public function candidates(): array
    {
        $inventory = collect($this->quebec->inventory());
        $sensitive = $inventory->where('category', DataCategory::Sensitive->value)->pluck('model')->unique()->all();
        $profiling = $inventory->where('profiling', true)->pluck('model')->unique()->all();
        $outside = $inventory->where('outside_quebec', true)->pluck('model')->unique()->all();

        $assessed = ImpactAssessment::query()->whereNotNull('processing_activity_id')->get(['processing_activity_id', 'type'])
            ->groupBy('processing_activity_id')
            ->map(fn ($rows) => $rows->map(fn (ImpactAssessment $a): string => $a->type->value)->all());

        $candidates = [];

        foreach (ProcessingActivity::query()->orderBy('name')->get() as $activity) {
            $types = $assessed->get($activity->getKey(), []);
            $models = $activity->models ?? [];
            $reasons = [];

            if (! in_array(AssessmentType::Project->value, $types, true)) {
                if (array_intersect($models, $sensitive) !== []) {
                    $reasons[] = 'sensitive';
                }

                if (array_intersect($models, $profiling) !== []) {
                    $reasons[] = 'profiling';
                }
            }

            if (! in_array(AssessmentType::OutsideQuebec->value, $types, true)
                && (($activity->outside_quebec ?? []) !== [] || array_intersect($models, $outside) !== [])) {
                $reasons[] = 'outside_quebec';
            }

            if ($reasons !== []) {
                $candidates[] = ['activity' => $activity, 'reasons' => $reasons];
            }
        }

        return $candidates;
    }

    /**
     * @return array<string, mixed>
     */
    public function export(ImpactAssessment $assessment): array
    {
        return [
            'title' => $assessment->title,
            'type' => $assessment->type->value,
            'processing_activity' => $assessment->processingActivity?->name,
            'description' => $assessment->description,
            'necessity' => $assessment->necessity,
            'risks' => array_map(fn (array $risk): array => [
                ...$risk,
                'threat' => $risk['threat']?->value,
                'level' => $risk['level']->value,
                'residual_level' => $risk['residual_level']->value,
            ], $assessment->scoredRisks()),
            'highest_residual_risk' => $assessment->highestRisk()?->value,
            'person_in_charge_opinion' => $assessment->person_in_charge_opinion,
            'destination' => $assessment->destination,
            'legal_framework' => $assessment->legal_framework,
            'adequate_protection' => $assessment->adequate_protection,
            'portability' => $assessment->portability,
            'allows_communication' => $assessment->allowsCommunication(),
            'status' => $assessment->status->value,
            'approved_at' => $assessment->approved_at?->toIso8601String(),
            'approved_by' => $assessment->approved_by,
            'next_review_at' => $assessment->next_review_at?->toIso8601String(),
        ];
    }
}
