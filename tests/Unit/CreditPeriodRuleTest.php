<?php

namespace Tests\Unit;

use App\Models\Credit\CreditPeriod;
use PHPUnit\Framework\TestCase;

class CreditPeriodRuleTest extends TestCase
{
    private function period(int $month, ?float $min): CreditPeriod
    {
        return new CreditPeriod(['month' => $month, 'min_amount' => $min]);
    }

    public function test_period_without_minimum_is_always_available(): void
    {
        $this->assertTrue($this->period(3, null)->availableFor(10));
        $this->assertTrue($this->period(6, null)->availableFor(200));
    }

    public function test_period_is_available_only_above_its_minimum(): void
    {
        $this->assertFalse($this->period(9, 200)->availableFor(200));      // 200 daxil — yox
        $this->assertFalse($this->period(12, 200)->availableFor(199.99));
        $this->assertTrue($this->period(12, 200)->availableFor(200.01));
        $this->assertFalse($this->period(15, 500)->availableFor(450));
        $this->assertTrue($this->period(15, 500)->availableFor(500.5));
    }
}
