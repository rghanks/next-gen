<?php

declare(strict_types=1);

use App\Domain\Lab\Exceptions\InvalidStateTransitionException;
use App\Domain\Lab\Models\LabParameter;
use App\Domain\Lab\Models\LabReferenceRange;
use App\Domain\Lab\Services\AccessionGenerator;
use App\Domain\Lab\Services\BillNumberGenerator;
use App\Domain\Lab\Services\FlagCalculator;
use App\Domain\Lab\Services\FormulaEvaluator;
use App\Domain\Lab\Services\ReferenceRangeResolver;
use App\Domain\Lab\States\LabState;

test('valid state machine transitions succeed', function () {
    expect(LabState::ORDERED->canTransitionTo(LabState::COLLECTED))->toBeTrue();
    expect(LabState::COLLECTED->canTransitionTo(LabState::RECEIVED))->toBeTrue();
    expect(LabState::RECEIVED->canTransitionTo(LabState::IN_PROCESS))->toBeTrue();
    expect(LabState::IN_PROCESS->canTransitionTo(LabState::RESULTED))->toBeTrue();
    expect(LabState::RESULTED->canTransitionTo(LabState::VERIFIED))->toBeTrue();
    expect(LabState::VERIFIED->canTransitionTo(LabState::RELEASED))->toBeTrue();

    // Reject flow: received can go back to ordered for recollect
    expect(LabState::RECEIVED->canTransitionTo(LabState::ORDERED))->toBeTrue();
    // Pathologist rejection: verified can go back to in_process
    expect(LabState::VERIFIED->canTransitionTo(LabState::IN_PROCESS))->toBeTrue();
});

test('invalid state machine transitions throw exception', function () {
    // Cannot skip states: ordered -> released
    expect(fn () => LabState::ORDERED->transitionTo(LabState::RELEASED))
        ->toThrow(InvalidStateTransitionException::class);

    // Cancelled only before resulted
    expect(LabState::ORDERED->canTransitionTo(LabState::CANCELLED))->toBeTrue();
    expect(LabState::IN_PROCESS->canTransitionTo(LabState::CANCELLED))->toBeTrue();
    expect(LabState::RESULTED->canTransitionTo(LabState::CANCELLED))->toBeFalse();
    expect(fn () => LabState::RESULTED->transitionTo(LabState::CANCELLED))
        ->toThrow(InvalidStateTransitionException::class);

    // Released cannot directly transition to another state
    expect(LabState::RELEASED->canTransitionTo(LabState::CANCELLED))->toBeFalse();
});

test('bill number generator produces incrementing numbers per financial year', function () {
    $generator = new BillNumberGenerator;
    $fy = '2026-27';

    $num1 = $generator->nextNumber('TEST_BILL', $fy);
    $num2 = $generator->nextNumber('TEST_BILL', $fy);

    expect($num2)->toBe($num1 + 1);
    expect($generator->formatBillNumber('TEST_BILL', $fy, $num2))->toContain('TEST_BILL/2026-27/');
});

test('accession generator produces unique formatted strings and compact specimen barcodes', function () {
    $generator = new AccessionGenerator;
    $acc1 = $generator->generate();
    $acc2 = $generator->generate();

    expect($acc1)->not->toBe($acc2);
    expect($acc1)->toMatch('/^ACC-\d{8}-\d{5}$/');

    $smp1 = $generator->generateBarcode();
    $smp2 = $generator->generateBarcode();

    expect($smp1)->not->toBe($smp2);
    expect($smp1)->toMatch('/^SMP-\d{6}$/');
});

test('reference range resolver matches age and sex', function () {
    $resolver = new ReferenceRangeResolver;

    $parameter = LabParameter::create([
        'service_id' => 99999,
        'name' => 'Hemoglobin Test',
        'short_code' => 'HGB_TEST',
        'unit' => 'g/dL',
        'decimal_places' => 1,
        'input_type' => 'numeric',
    ]);

    // Adult male range (18+ years: 6570 to 43800 days)
    LabReferenceRange::create([
        'parameter_id' => $parameter->id,
        'sex' => 'male',
        'age_from_days' => 6570,
        'age_to_days' => 43800,
        'low' => 13.5,
        'high' => 17.5,
        'critical_low' => 7.0,
        'critical_high' => 20.0,
        'text_range' => '13.5 - 17.5',
    ]);

    // Adult female range
    LabReferenceRange::create([
        'parameter_id' => $parameter->id,
        'sex' => 'female',
        'age_from_days' => 6570,
        'age_to_days' => 43800,
        'low' => 12.0,
        'high' => 15.5,
        'critical_low' => 6.5,
        'critical_high' => 19.0,
        'text_range' => '12.0 - 15.5',
    ]);

    // Test 30-year-old male (10950 days)
    $maleRange = $resolver->resolve($parameter, 10950, 'male');
    expect($maleRange['low'])->toBe(13.5);
    expect($maleRange['high'])->toBe(17.5);
    expect($maleRange['critical_low'])->toBe(7.0);

    // Test 30-year-old female
    $femaleRange = $resolver->resolve($parameter, 10950, 'female');
    expect($femaleRange['low'])->toBe(12.0);
    expect($femaleRange['high'])->toBe(15.5);
});

test('safe formula evaluation with Symfony ExpressionLanguage', function () {
    $evaluator = new FormulaEvaluator;

    // Simple multiplication
    $res1 = $evaluator->evaluate('param_hgb * 3', ['param_hgb' => 14.2], 1);
    expect($res1)->toBe(42.6);

    // Subtraction formula: Total Protein - Albumin = Globulin
    $res2 = $evaluator->evaluate('total_protein - albumin', [
        'total_protein' => 7.5,
        'albumin' => 4.2,
    ], 2);
    expect($res2)->toBe(3.30);

    // Division by zero or syntax error handled safely
    $resErr = $evaluator->evaluate('param_a / 0', ['param_a' => 10]);
    expect($resErr)->toBeNull();
});

test('flag calculator computes LL, HH, L, H, N correctly', function () {
    $calculator = new FlagCalculator;

    // Normal range 10 - 20, critical 5 - 25
    expect($calculator->calculate(15.0, 10.0, 20.0, 5.0, 25.0))->toBe('N');
    expect($calculator->calculate(8.0, 10.0, 20.0, 5.0, 25.0))->toBe('L');
    expect($calculator->calculate(22.0, 10.0, 20.0, 5.0, 25.0))->toBe('H');
    expect($calculator->calculate(4.0, 10.0, 20.0, 5.0, 25.0))->toBe('LL');
    expect($calculator->calculate(28.0, 10.0, 20.0, 5.0, 25.0))->toBe('HH');
    expect($calculator->calculate(null, 10.0, 20.0, 5.0, 25.0, 'REACTIVE'))->toBe('A');
});
