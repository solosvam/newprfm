<?php

namespace App\Services\Messenger;

use App\Models\Customer\Customer;
use App\Models\Customer\CustomerMessengerAccount;
use App\Services\BonusService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** Müştəri ↔ messencer hesabı: bağlama, ayırma, botdan qeydiyyat, saytdan bağlama linki */
class MessengerAccounts
{
    public const LINK_TTL_MINUTES = 30;

    public function find(string $channel, string $externalId): ?CustomerMessengerAccount
    {
        return CustomerMessengerAccount::with('customer')->where('channel', $channel)->where('external_id', $externalId)->first();
    }

    /**
     * Bağlayır. Bu messencer hesabı başqa müştəriyə, və ya müştərinin bu kanalda başqa hesabı bağlı idisə — köhnəsi silinir
     * (sonuncu təsdiq qüvvədədir). Nömrə kanal və ya SMS ilə təsdiqləndiyi üçün aktiv olmayan hesab aktivləşir.
     */
    public function link(Customer $customer, IncomingMessage $message, string $via, ?string $phone = null): CustomerMessengerAccount
    {
        return DB::transaction(function () use ($customer, $message, $via, $phone) {
            CustomerMessengerAccount::where('channel', $message->channel)
                ->where(fn ($q) => $q->where('external_id', $message->senderId)->orWhere('customer_id', $customer->id))
                ->delete();
            $this->activate($customer);

            return CustomerMessengerAccount::create([
                'customer_id' => $customer->id,
                'channel' => $message->channel,
                'external_id' => $message->senderId,
                'phone' => $phone,
                'display_name' => mb_substr(trim($message->firstName.' '.$message->lastName), 0, 100) ?: null,
                'linked_via' => $via,
            ]);
        });
    }

    /** Nömrəsi bazada olmayan — botdan yeni müştəri (ad/soyad messencer profilindən). Qeydiyyat bonusu — saytdakı qayda ilə. */
    public function register(IncomingMessage $message, string $mobile): Customer
    {
        return DB::transaction(function () use ($message, $mobile) {
            $customer = Customer::where('mobile', $mobile)->lockForUpdate()->first();
            if (!$customer) {
                $customer = Customer::create([
                    'name' => mb_substr($message->firstName, 0, 30) ?: 'Müştəri',
                    'surname' => mb_substr($message->lastName, 0, 30),
                    'mobile' => $mobile,
                    'gender' => null,
                    'email' => null,
                    'password' => null,
                    'active' => true,
                    'source' => $message->channel,
                ]);
                app(BonusService::class)->grantRegistration($customer);
            }

            return $customer;
        });
    }

    public function unlink(Customer $customer, string $channel): void
    {
        $customer->messengerAccounts()->where('channel', $channel)->delete();
    }

    /** Saytda daxil olmuş müştəri üçün birdəfəlik bağlama açarı (Telegram: t.me/bot?start=açar) */
    public function createLinkToken(Customer $customer): string
    {
        $token = Str::random(32);
        Cache::put('messenger:link:'.$token, $customer->id, now()->addMinutes(self::LINK_TTL_MINUTES));

        return $token;
    }

    public function consumeLinkToken(string $token): ?Customer
    {
        if (!preg_match('/^[A-Za-z0-9]{32}$/', $token)) {
            return null;
        }
        $id = Cache::pull('messenger:link:'.$token);

        return $id ? Customer::find($id) : null;
    }

    /** Saytda SMS-i təsdiqlənməmiş hesab — nömrə indi təsdiqləndi: aktivləşir, qeydiyyat bonusu (RegistrationOtpService kimi) */
    private function activate(Customer $customer): void
    {
        if ($customer->active) {
            return;
        }
        $customer->forceFill(['active' => true, 'registration_otp_hash' => null, 'registration_otp_expires_at' => null])->save();
        app(BonusService::class)->grantRegistration($customer);
    }
}
