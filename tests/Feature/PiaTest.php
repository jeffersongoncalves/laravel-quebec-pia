<?php

use Illuminate\Support\Facades\Artisan;
use JeffersonGoncalves\QuebecGovernance\Models\ProcessingActivity;
use JeffersonGoncalves\QuebecPia\Enums\AssessmentStatus;
use JeffersonGoncalves\QuebecPia\Enums\AssessmentType;
use JeffersonGoncalves\QuebecPia\Enums\RiskLevel;
use JeffersonGoncalves\QuebecPia\Enums\Threat;
use JeffersonGoncalves\QuebecPia\Facades\QuebecPia;
use JeffersonGoncalves\QuebecPia\Models\ImpactAssessment;
use JeffersonGoncalves\QuebecPia\Tests\Fixtures\Applicant;
use JeffersonGoncalves\QuebecPia\Tests\Fixtures\Customer;
use JeffersonGoncalves\QuebecPia\Tests\Fixtures\Patient;

function activity(string $name, array $models, array $outside = []): ProcessingActivity
{
    return ProcessingActivity::create(['name' => $name, 'purpose' => $name, 'models' => $models, 'outside_quebec' => $outside]);
}

function assessment(?ProcessingActivity $activity = null, AssessmentType $type = AssessmentType::Project): ImpactAssessment
{
    return ImpactAssessment::create([
        'title' => 'Telemedicine',
        'type' => $type,
        'processing_activity_id' => $activity?->getKey(),
        'risks' => [
            ['title' => 'Diagnosis leak', 'threat' => 'data_disclosure', 'likelihood' => '2', 'impact' => '3', 'measures' => 'Encryption at rest', 'residual_likelihood' => '1', 'residual_impact' => '3'],
            ['title' => 'Re-identification in reports', 'threat' => 'identifying', 'likelihood' => 1, 'impact' => 2],
        ],
    ]);
}

it('scores risks by likelihood and impact, before and after measures', function () {
    $risks = assessment()->scoredRisks();

    expect($risks[0])
        ->threat->toBe(Threat::DataDisclosure)
        ->level->toBe(RiskLevel::High)
        ->residual_level->toBe(RiskLevel::Medium)
        ->and($risks[1]['residual_level'])->toBe(RiskLevel::Low)
        ->and(assessment()->highestRisk(residual: false))->toBe(RiskLevel::High)
        ->and(assessment()->highestRisk())->toBe(RiskLevel::Medium)
        ->and(RiskLevel::score(3, 3))->toBe(RiskLevel::High)
        ->and(fn () => RiskLevel::score(4, 1))->toThrow(InvalidArgumentException::class);
});

it('approves, schedules a review and returns to draft when edited', function () {
    $pia = assessment()->approve('Marie Tremblay (person in charge)');

    expect($pia->status)->toBe(AssessmentStatus::Approved)
        ->and($pia->type)->toBe(AssessmentType::Project)
        ->and($pia->next_review_at->toDateString())->toBe(now()->addYear()->toDateString());

    $this->travel(13)->months();

    expect($pia->isReviewDue())->toBeTrue()
        ->and(ImpactAssessment::query()->scopes('dueForReview')->count())->toBe(1);

    $pia->update(['necessity' => 'Only the information needed for the appointment']);

    expect($pia->fresh())
        ->status->toBe(AssessmentStatus::Draft)
        ->approved_by->toBeNull()
        ->isReviewDue()->toBeFalse();
});

it('needs risks before approval', function () {
    ImpactAssessment::create(['title' => 'Empty'])->approve('Person in charge');
})->throws(LogicException::class, 'at least one risk');

it('allows a communication outside Quebec only once approved with adequate protection', function () {
    $pia = assessment(type: AssessmentType::OutsideQuebec);

    expect($pia->allowsCommunication())->toBeFalse()
        ->and(fn () => $pia->approve('Person in charge'))->toThrow(LogicException::class, 's. 17');

    $pia->update(['destination' => 'United States', 'legal_framework' => 'No federal privacy law; contract clauses', 'adequate_protection' => false]);
    $pia->approve('Person in charge');

    expect($pia->allowsCommunication())->toBeFalse();

    $pia->update(['adequate_protection' => true]);

    expect($pia->fresh()->status)->toBe(AssessmentStatus::Draft);

    expect($pia->fresh()->approve('Person in charge')->allowsCommunication())->toBeTrue()
        ->and(assessment()->allowsCommunication())->toBeTrue(); // a project assessment never blocks
});

it('lists activities that may need an assessment, by type', function () {
    $health = activity('Telemedicine', [Patient::class]);
    activity('Billing', [Customer::class]);
    activity('Recruiting', [Applicant::class]);
    $hosting = activity('Hosting', [Customer::class], [['place' => 'Ontario']]);

    expect(collect(QuebecPia::candidates())->map(fn (array $c) => [$c['activity']->name, $c['reasons']])->all())->toBe([
        ['Hosting', ['outside_quebec']],
        ['Recruiting', ['outside_quebec']],
        ['Telemedicine', ['sensitive']],
    ]);

    assessment($health);
    assessment($hosting); // a project assessment does not cover s. 17

    expect(QuebecPia::candidates())->toHaveCount(2);

    assessment($hosting, AssessmentType::OutsideQuebec);

    expect(QuebecPia::candidates())->toHaveCount(1);
});

it('reports assessments and candidates', function () {
    assessment(activity('Telemedicine', [Patient::class]))->approve('Person in charge');
    activity('Recruiting', [Applicant::class]);

    Artisan::call('quebec:pia');
    expect(Artisan::output())->toContain('Telemedicine')->toContain('project')->toContain('high (medium)')->toContain('No assessment for [Recruiting]: outside_quebec');

    Artisan::call('quebec:pia', ['--json' => true]);
    expect(json_decode(Artisan::output(), true)[0])
        ->processing_activity->toBe('Telemedicine')
        ->type->toBe('project')
        ->highest_residual_risk->toBe('medium')
        ->allows_communication->toBeTrue()
        ->risks->{0}->threat->toBe('data_disclosure');
});
