<?php

use App\Flood\Enums\SeverityLevel;

test('fromApiValue maps integer to correct enum case', function () {
    expect(SeverityLevel::fromApiValue(1))->toBe(SeverityLevel::Severe)
        ->and(SeverityLevel::fromApiValue(2))->toBe(SeverityLevel::Warning)
        ->and(SeverityLevel::fromApiValue(3))->toBe(SeverityLevel::Alert)
        ->and(SeverityLevel::fromApiValue(4))->toBe(SeverityLevel::Inactive);
});

test('fromApiValue handles null and empty as Inactive', function () {
    expect(SeverityLevel::fromApiValue(null))->toBe(SeverityLevel::Inactive)
        ->and(SeverityLevel::fromApiValue(''))->toBe(SeverityLevel::Inactive);
});

test('fromApiValue handles invalid values as Inactive', function () {
    expect(SeverityLevel::fromApiValue(99))->toBe(SeverityLevel::Inactive);
});

test('label returns human readable string', function () {
    expect(SeverityLevel::Severe->label())->toBe('Severe Flood Warning')
        ->and(SeverityLevel::Warning->label())->toBe('Flood Warning')
        ->and(SeverityLevel::Alert->label())->toBe('Flood Alert')
        ->and(SeverityLevel::Inactive->label())->toBe('Inactive');
});
