<?php

use App\Enums\SeverityLevel;

test('fromApiValue maps integer to correct enum case', function () {
    expect(SeverityLevel::fromApiValue(1))->toBe(SeverityLevel::Severe);
    expect(SeverityLevel::fromApiValue(2))->toBe(SeverityLevel::Warning);
    expect(SeverityLevel::fromApiValue(3))->toBe(SeverityLevel::Alert);
    expect(SeverityLevel::fromApiValue(4))->toBe(SeverityLevel::Inactive);
});

test('fromApiValue handles null and empty as Inactive', function () {
    expect(SeverityLevel::fromApiValue(null))->toBe(SeverityLevel::Inactive)
        ->and(SeverityLevel::fromApiValue(''))->toBe(SeverityLevel::Inactive);
});

test('fromApiValue handles invalid values as Inactive', function () {
    expect(SeverityLevel::fromApiValue(99))->toBe(SeverityLevel::Inactive);
});

test('label returns human readable string', function () {
    expect(SeverityLevel::Severe->label())->toBe('Severe Flood Warning');
    expect(SeverityLevel::Warning->label())->toBe('Flood Warning');
    expect(SeverityLevel::Alert->label())->toBe('Flood Alert');
    expect(SeverityLevel::Inactive->label())->toBe('Inactive');
});
