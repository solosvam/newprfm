<?php

namespace Tests\Unit\Credit;

use App\Models\Credit\CreditApplication;
use PHPUnit\Framework\TestCase;

class CreditAgreedTotalTest extends TestCase
{
    public function test_standard_when_not_requested(): void
    {
        $this->assertSame(330.0, CreditApplication::agreedTotal(330, 300, null));
    }

    public function test_campaign_total_between_interest_free_and_standard(): void
    {
        $this->assertSame(300.0, CreditApplication::agreedTotal(330, 300, 300));   // 6 ay faizsiz
        $this->assertSame(315.5, CreditApplication::agreedTotal(330, 300, 315.499));
        $this->assertSame(330.0, CreditApplication::agreedTotal(330, 300, 330));
    }

    public function test_out_of_range_is_rejected(): void
    {
        $this->assertNull(CreditApplication::agreedTotal(330, 300, 299.99)); // faizsizdən aşağı — endirim deyil, kredit
        $this->assertNull(CreditApplication::agreedTotal(330, 300, 330.01)); // standartdan yuxarı
    }
}
