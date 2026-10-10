<?php

namespace App\Models;

use App\Services\SmsService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Throwable;

class SmsLog extends Model
{
    public const SENT = 'sent';
    public const FAILED = 'failed';

    /** lsim çatdırılma hesabatı kodları (docs.lsim.az/quicksms.html) */
    public const DELIVERY = [
        100 => 'Növbədədir',
        101 => 'Çatdırılıb',
        102 => 'Çatdırılmadı',
        103 => 'Müddəti bitib',
        104 => 'Rədd edilib',
        105 => 'Ləğv edilib',
        106 => 'Xəta',
        107 => 'Naməlum',
        108 => 'Göndərilib',
        109 => 'Nömrə qara siyahıdadır',
    ];
    public const DELIVERED = 101;
    /** Hələ yekun deyil — yenidən yoxlanılır */
    public const DELIVERY_PENDING = [100, 107, 108];
    /** Müştəriyə / anbara çatmadı */
    public const DELIVERY_FAILED = [102, 103, 104, 105, 106, 109];

    /** Şablonsuz göndərilən SMS-lərin konteksti */
    public const CONTEXTS = [
        'otp' => 'Təsdiq kodu (OTP)',
        'crm_password_reset' => 'CRM — yeni şifrə',
    ];

    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['created_at' => 'datetime', 'delivery_checked_at' => 'datetime', 'delivery_status' => 'integer'];
    }

    public function isSent(): bool
    {
        return $this->status === self::SENT;
    }

    /** Göndərilmədi və ya göndərildi, amma çatmadı */
    public function isProblem(): bool
    {
        return !$this->isSent() || in_array($this->delivery_status, self::DELIVERY_FAILED, true);
    }

    public function scopeProblems($query)
    {
        return $query->where(fn ($q) => $q->where('status', self::FAILED)->orWhereIn('delivery_status', self::DELIVERY_FAILED));
    }

    /**
     * SMS göndərir və nəticəni jurnala yazır. Göndəriş alınmasa xəta yenə atılır (çağıran özü qərar verir),
     * amma jurnala "failed" kimi düşür. $secrets — jurnalda gizlədilən hissələr (təsdiq kodu, şifrə).
     *
     * @throws Throwable
     */
    public static function deliver(SmsService $sms, string $number, string $message, string $context, ?Model $subject = null, ?int $by = null, array $secrets = []): ?string
    {
        $row = [
            'context' => $context, 'subject_type' => $subject?->getMorphClass(), 'subject_id' => $subject?->getKey(),
            'msisdn' => $number, 'message' => str_replace(array_map('strval', $secrets), '***', $message),
            'created_by' => $by ?: null, 'created_at' => now(),
        ];
        try {
            $id = $sms->send($number, $message);
            static::record($row + ['status' => self::SENT, 'provider_id' => $id]);

            return $id;
        } catch (Throwable $e) {
            static::record($row + ['status' => self::FAILED, 'error' => Str::limit($e->getMessage(), 250)]);
            throw $e;
        }
    }

    /** Jurnal yazılmasa da SMS-in özü (və çağıran əməliyyat) pozulmamalıdır */
    private static function record(array $row): void
    {
        try {
            static::create($row);
        } catch (Throwable $e) {
            if (!app()->runningUnitTests()) {
                report($e);
            }
        }
    }
}
