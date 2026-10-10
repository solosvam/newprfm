<?php

namespace App\Services\Crm;

use App\Models\Customer\Customer;
use App\Models\SmsTemplate;
use App\Services\SmsService;
use Illuminate\Validation\Rule;

/** Operatorun müştəri yaratması: CRM ("Yeni müştəri" modalı) və WhatsApp paneli eyni qaydalarla */
class CustomerRegistration
{
    public function __construct(private SmsService $sms)
    {
    }

    /** 0103227575 / +994 10 322 75 75 → 994103227575 (tanınmırsa — rəqəmlər olduğu kimi, validasiya rədd edir) */
    public static function normalizeMobile(?string $value): string
    {
        $digits = preg_replace('/\D+/', '', (string) $value);

        return preg_match('/^(?:994|0)?([1-9]\d{8})$/', $digits, $m) ? '994'.$m[1] : $digits;
    }

    public static function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:30'],
            'surname' => ['required', 'string', 'max:30'],
            'mobile' => ['required', 'regex:/^994[1-9]\d{8}$/', Rule::unique('customers', 'mobile')],
            'email' => ['nullable', 'email', 'max:50', Rule::unique('customers', 'email')],
            'gender' => ['required', 'in:0,1'],
            'send_password' => ['nullable', 'boolean'],
        ];
    }

    public static function messages(): array
    {
        return [
            'mobile.regex' => 'Mobil nömrəni düzgün yazın (994XXXXXXXXX, 994-dən sonra 0 olmur).',
            'mobile.unique' => 'Bu nömrə ilə müştəri artıq var.',
            'email.unique' => 'Bu e-poçt ilə müştəri artıq var.',
            'gender.required' => 'Cinsi seçin.',
        ];
    }

    public static function attributes(): array
    {
        return ['name' => 'Ad', 'surname' => 'Soyad', 'mobile' => 'Mobil', 'email' => 'E-poçt'];
    }

    /**
     * Aktiv müştəri, təsadüfi şifrə; istənibsə şifrə SMS ilə.
     *
     * @return array{customer: Customer, sms: ?bool} sms: null — istənməyib, true — getdi, false — getmədi
     */
    public function register(array $data, bool $sendPassword, string $source = 'crm'): array
    {
        $password = (string) random_int(100000, 999999);
        $customer = Customer::create([
            'name' => $data['name'],
            'surname' => $data['surname'],
            'mobile' => $data['mobile'],
            'email' => $data['email'] ?? null,
            'gender' => (int) $data['gender'],
            'password' => bcrypt($password),
            'active' => true,
            'source' => $source,
        ]);

        $sms = null;
        if ($sendPassword) {
            try {
                // Şablon "SMS şablonları"ndan; deaktivdirsə və ya {password} silinibsə — standart mətn (şifrə mütləq getməlidir)
                $text = SmsTemplate::message('crm_customer_created', ['name' => $customer->name, 'fullname' => $customer->fullname, 'password' => $password]);
                if ($text === null || !str_contains($text, $password)) {
                    $text = "Hormetli {$customer->fullname}, Parfumshop hesabiniz yaradildi. Sifreniz: {$password}";
                }
                \App\Models\SmsLog::deliver($this->sms, $customer->mobile, $text, 'crm_customer_created', $customer, auth('admin')->id(), [$password]);
                $sms = true;
            } catch (\Throwable $e) {
                report($e);
                $sms = false;
            }
        }

        return ['customer' => $customer, 'sms' => $sms];
    }
}
