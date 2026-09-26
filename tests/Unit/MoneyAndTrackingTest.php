<?php

namespace Tests\Unit;

use App\Support\Money;
use App\Support\TrackingNumber;
use PHPUnit\Framework\TestCase;

class MoneyAndTrackingTest extends TestCase
{
    public function test_money_is_decimal_safe(): void
    {
        $this->assertSame(1235, Money::toMinor('12.345'));
        $this->assertSame(1234, Money::toMinor('12.344'));
        $this->assertSame(30, Money::toMinor('0.1') + Money::toMinor('0.2'));
        $this->assertSame('0.30', Money::fromMinor(Money::toMinor('0.1') + Money::toMinor('0.2')));
        $this->assertSame(750, Money::percentOf(10000, '7.5'));
        $this->assertSame(1, Money::percentOf(10, '7.5')); // 0.75 rounds half-up to 1
        $this->assertSame('-5.00', Money::fromMinor(-500));
    }

    public function test_tracking_numbers_are_well_formed_and_unpredictable(): void
    {
        $seen = [];
        for ($i = 0; $i < 2000; $i++) {
            $t = TrackingNumber::generate('CX');
            $this->assertMatchesRegularExpression('/^CX[0-9A-HJKMNP-TV-Z]{13}$/', $t);
            $this->assertTrue(TrackingNumber::isWellFormed($t));
            $seen[$t] = true;
        }
        $this->assertCount(2000, $seen, 'Generated numbers must not collide in a small sample.');
    }

    public function test_checksum_catches_typos_and_normalises_input(): void
    {
        for ($n = 0; $n < 500; $n++) {
            $t = TrackingNumber::generate('CX');
            $body = substr($t, 2, 12);
            $swapped = 'CX'.$body[1].$body[0].substr($body, 2).substr($t, -1);
            if ($body[0] !== $body[1] && ! in_array($body[0].$body[1], ['0Z', 'Z0'], true)) {
                $this->assertFalse(TrackingNumber::isWellFormed($swapped), "Transposition not detected: $t");
            }
            $mutated = substr($t, 0, 7).($t[7] === 'A' ? 'B' : 'A').substr($t, 8);
            $this->assertFalse(TrackingNumber::isWellFormed($mutated), "Substitution not detected: $t");
        }
        $this->assertFalse(TrackingNumber::isWellFormed('CX123'));
        $this->assertSame($t, TrackingNumber::normalize(' '.strtolower(substr($t, 0, 6)).'-'.strtolower(substr($t, 6)).' '));
    }
}
