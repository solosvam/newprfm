<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class SmsService
{
    public function send(string $number, string $message): void
    {
        $number = $this->normalize($number);
        $login = (string) config('services.parfumshop_sms.login');
        $password = (string) config('services.parfumshop_sms.password');
        $sender = (string) config('services.parfumshop_sms.sender', 'ParfumShop');
        $url = (string) config('services.parfumshop_sms.url');

        if (!$login || !$password || !$sender || !$url) {
            throw new RuntimeException('SMS service konfiqurasiyası tamamlanmayıb.');
        }

        $pass = md5($password);
        $key = md5($pass.$login.$message.$number.$sender);

        $response = Http::timeout(10)->get($url, [
            'login' => $login,
            'msisdn' => $number,
            'text' => $message,
            'sender' => $sender,
            'key' => $key,
        ]);

        if (!$response->successful()) {
            throw new RuntimeException('SMS göndərilmədi.');
        }
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
