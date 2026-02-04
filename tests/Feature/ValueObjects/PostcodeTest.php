<?php

namespace Tests\Feature\ValueObjects;

use App\Enums\Region;
use App\ValueObjects\Postcode;
use Tests\TestCase;

class PostcodeTest extends TestCase
{
    public function test_try_from_accepts_valid_full_postcode(): void
    {
        $postcode = Postcode::tryFrom('TA10 0DP');
        $this->assertNotNull($postcode);
        $this->assertSame('TA10', $postcode->outcode());
        $this->assertSame('TA', $postcode->areaCode());
        $this->assertTrue($postcode->isInSouthWest());
        $this->assertSame(Region::Somerset, $postcode->region());
    }

    public function test_try_from_accepts_outcode_only(): void
    {
        $postcode = Postcode::tryFrom('TA10 0');
        $this->assertNotNull($postcode);
        $this->assertSame('TA10', $postcode->outcode());
    }

    public function test_try_from_normalizes_whitespace(): void
    {
        $postcode = Postcode::tryFrom('  ta10   0dp  ');
        $this->assertNotNull($postcode);
        $this->assertSame('TA10 0DP', $postcode->normalize());
    }

    public function test_try_from_returns_null_for_empty_string(): void
    {
        $this->assertNull(Postcode::tryFrom(''));
        $this->assertNull(Postcode::tryFrom('   '));
    }

    public function test_try_from_returns_null_for_invalid_format(): void
    {
        $this->assertNull(Postcode::tryFrom('INVALID'));
        $this->assertNull(Postcode::tryFrom('12345'));
    }

    public function test_constructor_throws_for_invalid_postcode(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new Postcode('INVALID');
    }

    public function test_is_in_south_west_returns_false_for_out_of_area(): void
    {
        $postcode = Postcode::tryFrom('SW1A 1AA');
        $this->assertNotNull($postcode);
        $this->assertFalse($postcode->isInSouthWest());
    }
}
