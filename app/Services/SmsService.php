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

    private function normalize(string $number): string
    {
        $number = preg_replace('/\D+/', '', $number);

        if (str_starts_with($number, '994')) {
            return $number;
        }

        return '994'.ltrim($number, '0');
    }
}
