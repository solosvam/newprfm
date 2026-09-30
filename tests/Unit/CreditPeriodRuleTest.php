<?php

namespace Tests\Unit;

use App\Models\Credit\CreditPeriod;
use PHPUnit\Framework\TestCase;

class CreditPeriodRuleTest extends TestCase
{
    private function period(int $month): CreditPeriod
    {
        return new CreditPeriod(['month' => $month]);
    }

    public function test_small_amount_only_three_and_six_months(): void
    {
        $this->assertTrue($this->period(3)->availableFor(150));
        $this->assertTrue($this->period(6)->availableFor(200));      // 200 daxil
        $this->assertFalse($this->period(9)->availableFor(200));
        $this->assertFalse($this->period(12)->availableFor(199.99));
        $this->assertFalse($this->period(2)->availableFor(50));
    }

    public function test_above_limit_all_months(): void
    {
        $this->assertTrue($this->period(12)->availableFor(200.01));
        $this->assertTrue($this->period(15)->availableFor(450));
        $this->assertTrue($this->period(3)->availableFor(450));
    }

    public function test_rule_for_js(): void
    {
        $this->assertSame(['limit' => 200, 'months' => [3, 6]], CreditPeriod::amountRule());
    }
}
