<?php

namespace Tests\Unit;

use App\Models\Customer\CustomerCreditProfile;
use PHPUnit\Framework\TestCase;

class CreditProfileCompleteTest extends TestCase
{
    /** Qohumlar və iş yeri yoxdur — saytda müştəri üçün natamam, CRM-də operator üçün tamam */
    public function test_relatives_and_workplace_are_optional_only_for_staff(): void
    {
        $profile = new CustomerCreditProfile();
        $profile->forceFill([
            'father_name' => 'Əli', 'fin' => 'ABC1234', 'id_card_series' => 'AA', 'id_card_number' => '1234567',
            'id_card_front' => 'front.jpg', 'id_card_back' => 'back.jpg', 'salary' => 800,
        ]);

        $this->assertFalse($profile->isComplete());
        $this->assertTrue($profile->isComplete(staff: true));

        $profile->salary = null;
        $this->assertFalse($profile->isComplete(staff: true), 'əmək haqqı CRM-də də məcburidir');
    }
}
