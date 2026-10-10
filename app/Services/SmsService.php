<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class SmsService
{
    /** lsim QUICKSMS xəta kodları (docs.lsim.az/quicksms.html) */
    public const ERRORS = [
        -100 => 'Yanlış açar (login/parol)',
        -101 => 'Mətn çox uzundur',
        -102 => 'Nömrə formatı yanlışdır',
        -103 => 'Göndərən adı yanlışdır',
        -104 => 'SMS balansı bitib',
        -105 => 'Nömrə qara siyahıdadır',
        -106 => 'Yanlış tranzaksiya nömrəsi',
        -107 => 'IP ünvanına icazə yoxdur',
        -108 => 'Yanlış hash',
        -109 => 'Host yoxdur',
        -110 => 'Hesabat sorğularının dəqiqəlik limiti dolub',
        -500 => 'SMS provayderində daxili xəta',
    ];

    /**
     * SMS göndərir və provayderin tranzaksiya id-sini qaytarır.
     * lsim HTTP 200 ilə də xəta qaytarır ({"errorCode": -104, ...}) — ona görə JSON yoxlanılır.
     * Latın əlifbasından kənar simvol (ə, ş, ç, ...) olarsa unicode=true (1 SMS = 70 simvol).
     *
     * @throws RuntimeException
     */
    public function send(string $number, string $message): ?string
    {
        $number = $this->normalize($number);
        $login = (string) config('services.parfumshop_sms.login');
        $password = (string) config('services.parfumshop_sms.password');
        $sender = (string) config('services.parfumshop_sms.sender', 'ParfumShop');
        $url = (string) config('services.parfumshop_sms.url');

        if (!$login || !$password || !$sender || !$url) {
            throw new RuntimeException('SMS service konfiqurasiyası tamamlanmayıb.');
        }
        if (strlen($number) !== 12) {
            throw new RuntimeException('Telefon nömrəsi yanlışdır.');
        }

        $pass = md5($password);
        $key = md5($pass.$login.$message.$number.$sender);
        $query = ['login' => $login, 'msisdn' => $number, 'text' => $message, 'sender' => $sender, 'key' => $key];
        if (!self::isGsm($message)) {
            $query['unicode'] = 'true';
        }

        $response = Http::timeout(10)->get($url, $query);

        if (!$response->successful()) {
            throw new RuntimeException('SMS göndərilmədi (HTTP '.$response->status().').');
        }

        $data = $response->json();
        $code = is_array($data) ? (int) ($data['errorCode'] ?? 0) : 0;
        if ($code < 0) {
            throw new RuntimeException('SMS göndərilmədi: '.(self::ERRORS[$code] ?? ($data['errorMessage'] ?? 'xəta '.$code)).'.');
        }

        return is_array($data) && isset($data['obj']) ? (string) $data['obj'] : null;
    }

    /**
     * Hesabdakı SMS balansı (lsim "balance"; açar = md5(md5(parol) + login)).
     *
     * @throws RuntimeException
     */
    public function balance(): float
    {
        [$login, $password] = $this->credentials();
        $data = $this->call(Http::timeout(10)->get($this->endpoint('balance'), ['login' => $login, 'key' => md5(md5($password).$login)]), 'SMS balansı alınmadı');

        return (float) ($data['obj'] ?? 0);
    }

    /**
     * Göndərilmiş SMS-in çatdırılma statusu (SmsLog::DELIVERY kodu) — lsim "report", tranzaksiya nömrəsinə görə.
     * Limit: dəqiqədə 150 sorğu (aşılanda -110).
     *
     * @throws RuntimeException
     */
    public function deliveryStatus(string $transactionId): int
    {
        [$login] = $this->credentials();
        $response = Http::timeout(10)->get($this->endpoint('report'), ['login' => $login, 'trans_id' => $transactionId]);
        if (!$response->successful()) {
            throw new RuntimeException('SMS hesabatı alınmadı (HTTP '.$response->status().').');
        }

        // Cavabın formatı sənəddə yazılmayıb: ya tək rəqəm ("101", xətada "-106"), ya da göndərişdəki kimi JSON ({"obj": 101, "errorCode": 0})
        $data = $response->json();
        if (is_array($data)) {
            $error = (int) ($data['errorCode'] ?? 0);
            $status = $data['obj'] ?? $data['status'] ?? null;
            $status = is_array($status) ? ($status['status'] ?? null) : $status;
            $status = $error < 0 ? $error : $status;
        } else {
            $status = trim($response->body());
        }
        if (!is_numeric($status)) {
            throw new RuntimeException('SMS hesabatı alınmadı: gözlənilməz cavab "'.\Illuminate\Support\Str::limit(trim($response->body()), 120).'".');
        }
        $status = (int) $status;
        if ($status < 0) {
            throw new RuntimeException('SMS hesabatı alınmadı: '.(self::ERRORS[$status] ?? 'xəta '.$status).'.', $status);
        }

        return $status;
    }

    /** @return array{0: string, 1: string} */
    private function credentials(): array
    {
        $login = (string) config('services.parfumshop_sms.login');
        $password = (string) config('services.parfumshop_sms.password');
        if (!$login || !$password) {
            throw new RuntimeException('SMS service konfiqurasiyası tamamlanmayıb.');
        }

        return [$login, $password];
    }

    /** Göndəriş ünvanının yanındakı digər lsim ünvanları (…/quicksms/v1/balance, …/report) */
    private function endpoint(string $name): string
    {
        return preg_replace('~/[^/]+$~', '/'.$name, (string) config('services.parfumshop_sms.url'));
    }

    private function call(\Illuminate\Http\Client\Response $response, string $what): array
    {
        if (!$response->successful()) {
            throw new RuntimeException($what.' (HTTP '.$response->status().').');
        }
        $data = $response->json();
        if (!is_array($data)) {
            throw new RuntimeException($what.': cavab düzgün formatda deyil.');
        }
        $code = (int) ($data['errorCode'] ?? 0);
        if ($code < 0) {
            throw new RuntimeException($what.': '.(self::ERRORS[$code] ?? ($data['errorMessage'] ?? 'xəta '.$code)).'.', $code);
        }

        return $data;
    }

    /** GSM 03.38 əsas əlifbası ilə yazılıbmı (unicode lazım deyil) */
    public static function isGsm(string $text): bool
    {
        return (bool) preg_match('/^[A-Za-z0-9 \r\n@£$¥èéùìòÇØøÅåΔ_ΦΓΛΩΠΨΣΘΞÆæßÉ!"#¤%&\'()*+,\-.\/:;<=>?¡ÄÖÑÜ§¿äöñüà^{}\\\\\[~\]|€]*$/u', $text);
    }

    public function history(string $number): array
    {
        $number = $this->normalize($number);
        $login = (string) config('services.parfumshop_sms.login');
        $password = (string) config('services.parfumshop_sms.password');
        $url = (string) config('services.parfumshop_sms.history_url');

        if (!$login || !$password || !$url) {
            throw new RuntimeException('SMS tarixçəsi üçün konfiqurasiya tamamlanmayıb.');
        }

        $pass = md5($password);
        $key = md5($pass . $login . $number);

        $response = Http::timeout(15)
            ->acceptJson()
            ->get($url, [
                'login' => $login,
                'key' => $key,
                'msisdn' => $number,
                'date' => now()->subDays(30)->toDateString(),
                'days' => 30,
            ]);

        if (!$response->successful()) {
            throw new RuntimeException('SMS tarixçəsi hazırda alına bilmədi.');
        }

        $data = $response->json();

        if (!is_array($data)) {
            throw new RuntimeException('SMS tarixçəsi düzgün formatda qaytmadı.');
        }

        if (isset($data['errorCode'])) {
            throw new RuntimeException((string) ($data['errorMessage'] ?? 'SMS tarixçəsi alına bilmədi.'));
        }

        $statuses = [
            1 => 'Müddəti bitib',
            2 => 'Çatdırılıb',
            3 => 'Çatdırılmadı',
            4 => 'Göndərildi',
            5 => 'Sistem xətası',
            6 => 'Nömrə qara siyahıda',
            7 => 'Mesaj növbədədir',
            8 => 'Təkrar mesaj',
        ];

        return collect($data['data'] ?? [])
            ->filter(fn (array $message) => isset($statuses[$message['status'] ?? null]))
            ->map(fn (array $message) => [
                'date' => $message['date'] ?? '—',
                'message' => $message['message'] ?? '—',
                'status' => $statuses[$message['status']],
            ])
            ->reverse()
            ->values()
            ->all();
    }

    private function normalize(string $number): string
    {
        $number = preg_replace('/\D+/', '', $number);

        if (str_starts_with($number, '994')) {
            return $number;
        }

        return '994'.ltrim($number, '0');
    }
}
