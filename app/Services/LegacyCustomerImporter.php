<?php

namespace App\Services;

use App\Models\Customer\Customer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use InvalidArgumentException;
use RuntimeException;

class LegacyCustomerImporter
{
    // Also track dry-run records so duplicates across API pages behave like a real import.
    private array $seenIds = [];
    private array $seenPhones = [];
    private array $seenEmails = [];

    public function page(int $after, int $limit, ?int $snapshot): array
    {
        $url = (string) config('services.legacy_customers.url');
        $token = (string) config('services.legacy_customers.token');
        $parts = parse_url($url);
        if (!$parts || ($parts['scheme'] ?? '') !== 'https' || empty($parts['host']) || isset($parts['user']) || isset($parts['pass'])) {
            throw new RuntimeException('LEGACY_CUSTOMER_EXPORT_URL düzgün HTTPS ünvanı olmalıdır.');
        }
        if (strlen($token) < 32) {
            throw new RuntimeException('LEGACY_CUSTOMER_EXPORT_TOKEN təyin edilməlidir (ən azı 32 simvol).');
        }
        $parameters = ['after_id' => $after, 'limit' => $limit];
        if ($snapshot !== null) $parameters['snapshot_max_id'] = $snapshot;
        try {
            $response = Http::acceptJson()->withHeaders(['X-Customer-Export-Token' => $token])
                ->withoutRedirecting()->connectTimeout(10)->timeout(60)->get($url, $parameters);
        } catch (\Throwable $e) {
            // Do not include request objects, tokens or response customer data in errors.
            throw new RuntimeException('Köhnə API-yə bağlantı alınmadı. Ünvanı və şəbəkəni yoxlayın.');
        }
        if (!$response->successful()) {
            throw new RuntimeException('Köhnə API HTTP '.$response->status().' qaytardı. Tokeni və endpoint-i yoxlayın.');
        }
        $data = $response->json();
        if (!is_array($data)) {
            throw new RuntimeException('Köhnə API təmiz JSON qaytarmır (HTML və ya başqa mətn gəlir). Köhnədə customer-export.php giriş faylını yerləşdirin və LEGACY_CUSTOMER_EXPORT_URL ünvanını ona dəyişin.');
        }
        $p = is_array($data) ? ($data['pagination'] ?? null) : null;
        if (!is_array($data) || ($data['version'] ?? null) !== 1 || !is_array($data['customers'] ?? null)
            || !array_is_list($data['customers']) || !is_array($p)
            || !is_int($p['snapshot_max_id'] ?? null) || $p['snapshot_max_id'] < $after
            || ($snapshot !== null && $snapshot !== $p['snapshot_max_id'])
            || ($p['after_id'] ?? null) !== $after || ($p['limit'] ?? null) !== $limit
            || ($p['count'] ?? null) !== count($data['customers']) || count($data['customers']) > $limit
            || !is_bool($p['has_more'] ?? null) || !array_key_exists('next_after_id', $p)) {
            throw new RuntimeException('Köhnə API-nin cavabı və ya səhifələmə məlumatı gözlənilən formatda deyil.');
        }
        $last = $after;
        foreach ($data['customers'] as $row) {
            if (!is_array($row) || !is_int($row['customer_id'] ?? null) || $row['customer_id'] <= $last || $row['customer_id'] > $p['snapshot_max_id']) {
                throw new RuntimeException('API customer_id sırası və ya snapshot həddi yanlışdır.');
            }
            $last = $row['customer_id'];
        }
        if (($p['last_customer_id'] ?? null) !== $last
            || ($p['has_more'] && ($last === $after || $p['next_after_id'] !== $last))
            || (!$p['has_more'] && $p['next_after_id'] !== null)) {
            throw new RuntimeException('API davametmə cursor-u yanlışdır.');
        }

        return $data;
    }

    /** @return array{status: string, old_customer_id: mixed, reason?: string, bonus_cents?: int} */
    public function import(array $row, bool $apply): array
    {
        $id = $row['customer_id'] ?? null;
        $date = $row['date_added'] ?? null;
        if (Validator::make(['date_added' => $date], ['date_added' => ['required', 'string', 'date_format:Y-m-d H:i:s']])->fails()) {
            return ['status' => 'invalid', 'old_customer_id' => $id, 'reason' => 'Uyğunsuz sahələr: date_added'];
        }
        if (is_int($id) && $id > 0 && isset($this->seenIds[$id])) {
            return ['status' => 'already_imported', 'old_customer_id' => $id];
        }
        if (is_int($id) && $id > 0 && ($existing = Customer::where('old_customer_id', $id)->first())) {
            if ($existing->created_at?->format('Y-m-d H:i:s') !== $date) {
                if ($apply) {
                    // Query builder updates only this column; Eloquent would also touch updated_at.
                    DB::table('customers')->where('id', $existing->id)->update(['created_at' => $date]);
                }
                $this->seenIds[$id] = true;
                return ['status' => $apply ? 'dates_updated' : 'would_update_date', 'old_customer_id' => $id];
            }
            return ['status' => 'already_imported', 'old_customer_id' => $id];
        }
        $data = [
            'old_customer_id' => $id,
            'name' => is_string($row['firstname'] ?? null) ? trim($row['firstname']) : null,
            'surname' => is_string($row['lastname'] ?? null) ? trim($row['lastname']) : null,
            'email' => is_string($row['email'] ?? null) ? (trim($row['email']) ?: null) : ($row['email'] ?? null),
            'mobile' => $row['telephone'] ?? null,
            'sex' => $row['sex'] ?? null,
            'bonus' => $row['bonus'] ?? null,
        ];
        $validator = Validator::make($data, [
            'old_customer_id' => ['required', 'integer', 'min:1'],
            'name' => ['required', 'string', 'max:30'],
            'surname' => ['required', 'string', 'max:30'],
            'email' => ['nullable', 'string', 'email', 'max:50'],
            'mobile' => ['required', 'string', 'regex:/^994[0-9]{9}$/D'],
            'sex' => ['required', 'integer', 'in:1,2'],
            'bonus' => ['required', 'string', 'regex:/^-?[0-9]{1,10}(?:\.[0-9]{1,6})?$/D'],
        ]);
        if ($validator->fails()) {
            return ['status' => 'invalid', 'old_customer_id' => $id, 'reason' => 'Uyğunsuz sahələr: '.implode(', ', $validator->errors()->keys())];
        }
        try {
            $cents = $this->bonusCents($data['bonus']);
        } catch (InvalidArgumentException $e) {
            return ['status' => 'invalid', 'old_customer_id' => $id, 'reason' => $e->getMessage()];
        }
        $emailKey = $data['email'] === null ? null : mb_strtolower($data['email']);

        $result = DB::transaction(function () use ($data, $apply, $id, $cents, $emailKey, $date) {
            if (Customer::where('old_customer_id', $id)->exists()) {
                return ['status' => 'already_imported', 'old_customer_id' => $id];
            }
            if (isset($this->seenPhones[$data['mobile']]) || Customer::where('mobile', $data['mobile'])->exists()) {
                return ['status' => 'conflict', 'old_customer_id' => $id, 'reason' => 'Mobil nömrə artıq mövcuddur.'];
            }
            if ($emailKey !== null && (isset($this->seenEmails[$emailKey]) || Customer::whereRaw('LOWER(email) = ?', [$emailKey])->exists())) {
                return ['status' => 'conflict', 'old_customer_id' => $id, 'reason' => 'E-poçt artıq mövcuddur.'];
            }
            if ($apply) {
                $customer = new Customer([
                    'old_customer_id' => $id, 'name' => $data['name'], 'surname' => $data['surname'],
                    'email' => $data['email'], 'mobile' => $data['mobile'], 'gender' => (int) $data['sex'] === 1 ? 1 : 0,
                    'password' => null, 'active' => true, 'bonus_balance' => self::decimal($cents),
                ]);
                $customer->created_at = $date;
                $customer->save();
                if ($cents > 0) {
                    $customer->bonusTransactions()->create([
                        'type' => 'adjustment', 'amount' => self::decimal($cents),
                        'note' => 'Köhnə sistemdən köçürülən bonus',
                    ]);
                }
            }
            return ['status' => $apply ? 'created' : 'would_create', 'old_customer_id' => $id, 'bonus_cents' => $cents];
        });
        if (in_array($result['status'], ['created', 'would_create'], true)) {
            $this->seenIds[$id] = true;
            $this->seenPhones[$data['mobile']] = true;
            if ($emailKey !== null) $this->seenEmails[$emailKey] = true;
        }

        return $result;
    }

    private function bonusCents(string $value): int
    {
        [$whole, $fraction] = array_pad(explode('.', $value, 2), 2, '');
        if (str_starts_with($whole, '-') || (int) $whole < 1) return 0;
        $fraction = str_pad($fraction, 3, '0');
        $cents = ((int) $whole * 100) + (int) substr($fraction, 0, 2) + ((int) $fraction[2] >= 5 ? 1 : 0);
        if ($cents > 999999999999) throw new InvalidArgumentException('Bonus balansı decimal(12,2) həddini keçir.');
        return $cents;
    }

    public static function decimal(int $cents): string
    {
        return intdiv($cents, 100).'.'.str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
    }
}
