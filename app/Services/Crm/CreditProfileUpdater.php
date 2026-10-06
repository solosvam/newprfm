<?php

namespace App\Services\Crm;

use App\Models\Customer\Customer;
use App\Models\Customer\CustomerCreditProfile;
use App\Services\IdCard\IdCardStorage;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/** Operatorun kredit profilini doldurması: CRM ("Kredit profili" tabı) və WhatsApp paneli eyni qaydalarla */
class CreditProfileUpdater
{
    /** Yoxlayır (ValidationException), vəsiqə şəkillərini saxlayır, profili yaradır/yeniləyir */
    public function update(Customer $customer, Request $request): CustomerCreditProfile
    {
        $profile = $customer->creditProfile;
        $request->merge([
            'fin' => strtoupper(trim((string) $request->input('fin'))),
            'id_card_number' => strtoupper(preg_replace('/\s+/', '', (string) $request->input('id_card_number'))),
        ]);

        $data = $request->validate([
            'father_name' => ['required', 'string', 'max:100'],
            'fin' => ['required', 'regex:/^[A-Z0-9]{7}$/', Rule::unique('customer_credit_profiles', 'fin')->ignore($profile?->id)],
            'id_card_series' => ['required', Rule::in(CustomerCreditProfile::ID_CARD_SERIES)],
            'id_card_number' => ['required', 'regex:/^[A-Z0-9]{1,8}$/'],
            'relative_1_name' => ['required', 'string', 'max:100'],
            'relative_1_phone' => ['required', 'regex:/^\+?[0-9 ]{9,16}$/'],
            'relative_2_name' => ['required', 'string', 'max:100'],
            'relative_2_phone' => ['required', 'regex:/^\+?[0-9 ]{9,16}$/'],
            'workplace_name' => ['required', 'string', 'max:255'],
            'salary' => ['required', 'numeric', 'gt:0', 'max:99999999.99'],
            'id_card_front' => [$profile?->id_card_front ? 'nullable' : 'required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'id_card_back' => [Rule::requiredIf(fn () => !$profile?->id_card_back
                && CustomerCreditProfile::needsBackSide($request->input('id_card_series'))), 'nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);

        foreach (['id_card_front', 'id_card_back'] as $field) {
            unset($data[$field]);
            if ($request->hasFile($field)) {
                $file = $request->file($field);
                $filename = Str::uuid().'.'.$file->getClientOriginalExtension();
                $file->move(IdCardStorage::ensureDirectory(), $filename);
                $data[$field] = $filename;
                IdCardStorage::delete($profile?->{$field});
            }
        }

        return $customer->creditProfile()->updateOrCreate([], $data);
    }
}
