<?php

namespace Tests\Unit;

use App\Models\Customer\CustomerCreditProfile;
use PHPUnit\Framework\TestCase;

class CreditProfileCompleteTest extends TestCase
{
    private function profile(array $overrides = []): CustomerCreditProfile
    {
        return new CustomerCreditProfile(array_merge([
            'father_name' => 'Əli', 'fin' => 'ABC1234', 'id_card_series' => 'AA', 'id_card_number' => '12345678',
            'relative_1_name' => 'Aynur', 'relative_1_phone' => '0501234567',
            'relative_2_name' => 'Rauf', 'relative_2_phone' => '0551234567',
            'id_card_front' => 'a.webp', 'id_card_back' => null, 'workplace_name' => 'ABC MMC', 'salary' => 900,
        ], $overrides));
    }

    public function test_new_card_needs_only_front_side(): void
    {
        $this->assertTrue($this->profile()->isComplete()); // AA, arxa üz yoxdur, vəzifə tələb olunmur
        $this->assertTrue($this->profile(['id_card_series' => 'MYİ'])->isComplete());
    }

    public function test_old_card_needs_back_side(): void
    {
        $this->assertFalse($this->profile(['id_card_series' => 'AZE'])->isComplete());
        $this->assertTrue($this->profile(['id_card_series' => 'AZE', 'id_card_back' => 'b.webp'])->isComplete());
    }

    public function test_series_and_number_are_required(): void
    {
        $this->assertFalse($this->profile(['id_card_series' => null])->isComplete());
        $this->assertFalse($this->profile(['id_card_number' => ''])->isComplete());
        $this->assertFalse($this->profile(['id_card_front' => null])->isComplete());
    }

    public function test_series_list(): void
    {
        $this->assertSame(['AA', 'AZE', 'AB', 'MYİ', 'DYİ', 'AA0', 'AA1', 'AA2', 'AA3', 'AAA', 'DY'], CustomerCreditProfile::ID_CARD_SERIES);
        $this->assertTrue(CustomerCreditProfile::needsBackSide('AZE'));
        $this->assertFalse(CustomerCreditProfile::needsBackSide('AA'));
        $this->assertFalse(CustomerCreditProfile::needsBackSide(null));
    }
}
