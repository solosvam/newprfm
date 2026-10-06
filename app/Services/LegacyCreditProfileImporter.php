<?php

namespace App\Services;

use App\Models\Customer\Customer;
use App\Models\Customer\CustomerCreditProfile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class LegacyCreditProfileImporter
{
    public function page(string $after, int $limit, ?int $snapshot): array
    {
        $url = (string) config('services.legacy_credit_profiles.url');
        $parts = parse_url($url);
        $token = (string) config('services.legacy_customers.token');
        if (!$parts || ($parts['scheme'] ?? '') !== 'https' || empty($parts['host']) || isset($parts['user']) || isset($parts['pass']) || strlen($token) < 32) {
            throw new RuntimeException('Kredit export URL-si və mövcud customer export tokenini yoxlayın.');
        }
        $query = ['after_mobile' => $after, 'limit' => $limit];
        if ($snapshot !== null) $query['snapshot_max_id'] = $snapshot;
        try {
            $response = Http::acceptJson()->withHeaders(['X-Customer-Export-Token' => $token])
                ->withoutRedirecting()->connectTimeout(10)->timeout(60)->get($url, $query);
        } catch (Throwable $e) {
            throw new RuntimeException('Köhnə kredit API-sinə bağlantı alınmadı.');
        }
        if (!$response->successful()) throw new RuntimeException('Kredit API-si HTTP '.$response->status().' qaytardı.');
        $data = $response->json();
        $p = is_array($data) ? ($data['pagination'] ?? null) : null;
        if (!is_array($data) || ($data['version'] ?? null) !== 1 || !is_array($data['groups'] ?? null) || !array_is_list($data['groups'])
            || !is_array($p) || !is_int($p['snapshot_max_id'] ?? null) || $p['snapshot_max_id'] < 0
            || ($snapshot !== null && $snapshot !== $p['snapshot_max_id']) || ($p['after_mobile'] ?? null) !== $after
            || ($p['limit'] ?? null) !== $limit || ($p['count'] ?? null) !== count($data['groups']) || count($data['groups']) > $limit
            || !is_bool($p['has_more'] ?? null) || !array_key_exists('next_after_mobile', $p)) {
            throw new RuntimeException('Kredit API-si gözlənilən JSON formatını qaytarmadı. Köhnə export fayllarını yoxlayın.');
        }
        $last = $after;
        foreach ($data['groups'] as $group) {
            if (!is_array($group) || !is_string($group['mobile'] ?? null) || !preg_match('/^994[0-9]{9}$/D', $group['mobile'])
                || strcmp($group['mobile'], $last) <= 0 || !is_array($group['records'] ?? null) || !array_is_list($group['records']) || !$group['records']) {
                throw new RuntimeException('Kredit API-si telefon qruplarını düzgün qaytarmadı.');
            }
            $previous = PHP_INT_MAX;
            foreach ($group['records'] as $record) {
                if (!is_array($record) || !is_int($record['id'] ?? null) || $record['id'] < 1 || $record['id'] >= $previous
                    || $record['id'] > $p['snapshot_max_id'] || ($record['mobile'] ?? null) !== $group['mobile']) {
                    throw new RuntimeException('Kredit API-si qeyd sırasını və ya telefon qrupunu düzgün qaytarmadı.');
                }
                $previous = $record['id'];
            }
            $last = $group['mobile'];
        }
        if (($p['last_mobile'] ?? null) !== $last || ($p['has_more'] && ($last === $after || $p['next_after_mobile'] !== $last))
            || (!$p['has_more'] && $p['next_after_mobile'] !== null)) {
            throw new RuntimeException('Kredit API-si davametmə cursor-unu düzgün qaytarmadı.');
        }
        return $data;
    }

    public static function card(?string $value): ?array
    {
        $value = preg_replace('/[\s№#-]+/u', '', mb_strtoupper(trim((string) $value)));
        if (!preg_match('/^(AZE|AA|AB|MYİ|DYİ|DY)([0-9]{1,8})$/uD', $value, $m)) return null;
        return ['id_card_series' => $m[1], 'id_card_number' => $m[2]];
    }

    private static function blank(mixed $value): bool
    {
        return $value === null || (is_string($value) && trim($value) === '');
    }

    private function fin(mixed $value): ?string
    {
        if (!is_string($value)) return null;
        $value = mb_strtoupper(preg_replace('/\s+/u', '', $value));
        return preg_match('/^[A-Z0-9]{7}$/D', $value) ? $value : null;
    }

    public function import(array $group, bool $apply): array
    {
        $ids = array_column($group['records'] ?? [], 'id');
        $result = ['source_ids' => $ids];
        $mobile = $group['mobile'] ?? null;
        if (!is_string($mobile) || !preg_match('/^994[0-9]{9}$/D', $mobile)) return $result + ['status' => 'invalid', 'reason' => 'mobile'];
        $customers = Customer::where('mobile', $mobile)->limit(2)->get();
        if ($customers->isEmpty()) return $result + ['status' => 'not_found'];
        if ($customers->count() !== 1) return $result + ['status' => 'conflict', 'reason' => 'Yeni sistemdə nömrə bir neçə müştəriyə aiddir.'];
        $customer = $customers->first();
        $result['customer_id'] = $customer->id;
        $profile = $customer->creditProfile;
        if ($profile?->isComplete()) return $result + ['status' => 'complete'];
        $records = $group['records'] ?? [];
        usort($records, fn ($a, $b) => $b['id'] <=> $a['id']);
        $fins = [];
        foreach ($records as $r) {
            if (($r['mobile'] ?? null) !== $mobile) return $result + ['status' => 'invalid', 'reason' => 'Telefon qrupu qarışıb.'];
            $fin = $this->fin($r['card_fin'] ?? null);
            if ($fin !== null) $fins[$fin] = true;
            elseif (!self::blank($r['card_fin'] ?? null)) return $result + ['status' => 'invalid', 'reason' => 'card_fin'];
        }
        if (count($fins) > 1) return $result + ['status' => 'conflict', 'reason' => 'Eyni nömrədə müxtəlif FIN-lər var.'];
        $fin = array_key_first($fins);
        if ($fin === null) return $result + ['status' => 'invalid', 'reason' => 'Şəxsiyyəti təsdiqləyən etibarlı FIN yoxdur.'];
        if (!self::blank($profile?->fin) && $this->fin($profile->fin) !== $fin) return $result + ['status' => 'conflict', 'reason' => 'Mövcud FIN fərqlidir.'];
        if (CustomerCreditProfile::whereRaw('UPPER(fin) = ?', [$fin])->where('customer_id', '!=', $customer->id)->exists()) {
            return $result + ['status' => 'conflict', 'reason' => 'FIN başqa müştərinin profilindədir.'];
        }
        // Rows without a FIN cannot provide identity documents or fill another person's profile.
        $records = array_values(array_filter($records, fn ($r) => $this->fin($r['card_fin'] ?? null) === $fin));
        $cards = [];
        $warnings = [];
        foreach ($records as $r) {
            $card = self::card(is_string($r['card_id'] ?? null) ? $r['card_id'] : null);
            if ($card) $cards[serialize($card)] = $card;
            elseif (!self::blank($r['card_id'] ?? null)) $warnings[] = 'card_id';
        }
        if (count($cards) > 1) return $result + ['status' => 'conflict', 'reason' => 'Eyni FIN üçün müxtəlif vəsiqələr var.'];
        $card = $cards ? reset($cards) : null;
        foreach (['id_card_series', 'id_card_number'] as $field) {
            if ($card && !self::blank($profile?->{$field}) && mb_strtoupper(trim($profile->{$field})) !== $card[$field]) {
                return $result + ['status' => 'conflict', 'reason' => 'Mövcud vəsiqə fərqlidir.'];
            }
        }
        $changes = [];
        if (self::blank($profile?->fin)) $changes['fin'] = $fin;
        if ($card) foreach ($card as $field => $value) if (self::blank($profile?->{$field})) $changes[$field] = $value;
        $mapping = ['fathername' => 'father_name', 'job_type' => 'workplace_name', 'job_salary' => 'salary',
            'relation_number1' => 'relative_1_phone', 'relation_number2' => 'relative_2_phone',
            'relation_number1_who' => 'relative_1_name', 'relation_number2_who' => 'relative_2_name'];
        $rules = ['father_name' => 'string|max:100', 'workplace_name' => 'string|max:255', 'salary' => 'numeric|gt:0|max:99999999.99',
            'relative_1_phone' => 'regex:/^\+?[0-9]{9,15}$/D', 'relative_2_phone' => 'regex:/^\+?[0-9]{9,15}$/D',
            'relative_1_name' => 'string|max:100', 'relative_2_name' => 'string|max:100'];
        foreach ($mapping as $source => $field) {
            if (!self::blank($profile?->{$field})) continue;
            foreach ($records as $r) {
                if (self::blank($r[$source] ?? null)) continue;
                if (!is_scalar($r[$source])) { $warnings[] = $field; continue; }
                $value = trim((string) $r[$source]);
                if (str_ends_with($field, '_phone')) $value = preg_replace('/[\s()\-]+/', '', $value);
                if ($field === 'salary') {
                    $value = str_replace(',', '.', preg_replace('/\s+/', '', $value));
                    if (!preg_match('/^[0-9]{1,8}(?:\.[0-9]{1,2})?$/D', $value)) { $warnings[] = $field; continue; }
                }
                if (Validator::make([$field => $value], [$field => $rules[$field]])->fails()) { $warnings[] = $field; continue; }
                $changes[$field] = $value;
                break;
            }
        }
        $gender = null;
        if (self::blank($customer->gender)) {
            foreach ($records as $r) {
                $value = strtolower(trim((string) ($r['gender'] ?? '')));
                if (in_array($value, ['male', 'female'], true)) { $gender = $value === 'male' ? 1 : 0; break; }
            }
        }
        $images = [];
        foreach (['id_front' => 'id_card_front', 'id_back' => 'id_card_back'] as $source => $field) {
            if (!self::blank($profile?->{$field})) continue;
            $seen = [];
            foreach ($records as $r) {
                // Documents must belong to the same parsed card, not merely the same phone.
                if (!$card || self::card(is_string($r['card_id'] ?? null) ? $r['card_id'] : null) !== $card) continue;
                $filename = $r[$source] ?? null;
                if (self::blank($filename) || !is_string($filename) || isset($seen[$filename])) continue;
                $seen[$filename] = true;
                try { $images[$field] = $this->image($filename); break; }
                catch (Throwable $e) { $warnings[] = $field; }
            }
        }
        $prospective = new CustomerCreditProfile();
        $prospective->forceFill($profile?->getAttributes() ?? []);
        $prospective->fill($changes);
        foreach ($images as $field => $image) $prospective->{$field} = 'pending.'.$image['extension'];
        $missing = [];
        // Both faces are checked in the import even when the normal profile only requires one.
        foreach (['father_name', 'fin', 'id_card_series', 'id_card_number', 'relative_1_name', 'relative_1_phone',
            'relative_2_name', 'relative_2_phone', 'workplace_name', 'salary', 'id_card_front', 'id_card_back'] as $field) {
            if (self::blank($prospective->{$field})) $missing[] = $field;
        }
        $fields = array_keys($changes + $images);
        if ($gender !== null) $fields[] = 'customers.gender';
        $result += ['fields' => $fields, 'missing' => $missing, 'warnings' => array_values(array_unique($warnings))];
        if (!$fields) return $result + ['status' => $missing || $warnings ? 'partial' : 'unchanged'];
        if (!$apply) return $result + ['status' => 'would_update'];

        $files = [];
        $originalCustomer = $customer->getAttributes();
        $originalProfile = $profile?->getAttributes();
        try {
            DB::transaction(function () use ($customer, $originalCustomer, $originalProfile, $changes, $images, $gender, &$files) {
                $fresh = Customer::whereKey($customer->id)->lockForUpdate()->firstOrFail();
                $current = $fresh->creditProfile()->lockForUpdate()->first();
                if ($fresh->getAttributes() !== $originalCustomer || $current?->getAttributes() !== $originalProfile) {
                    throw new RuntimeException('Profil import zamanı dəyişdi; yenidən yoxlayın.');
                }
                $data = $changes;
                if ($images) {
                    $directory = \App\Services\IdCard\IdCardStorage::ensureDirectory();
                    foreach ($images as $field => $image) {
                        $name = Str::uuid().'.'.$image['extension'];
                        $path = $directory.'/'.$name;
                        $files[] = $path;
                        if (file_put_contents($path, $image['body'], LOCK_EX) !== strlen($image['body'])) throw new RuntimeException('Şəkil saxlanmadı.');
                        $data[$field] = $name;
                    }
                }
                if ($data) $fresh->creditProfile()->updateOrCreate([], $data);
                if ($gender !== null) $fresh->update(['gender' => $gender]);
            });
        } catch (Throwable $e) {
            foreach ($files as $file) if (is_file($file)) unlink($file);
            throw new RuntimeException('Kredit profili saxlanmadı; həmin profil üzrə dəyişikliklər geri qaytarıldı.');
        }
        return $result + ['status' => 'updated'];
    }

    private function image(string $filename): array
    {
        if ($filename !== basename($filename) || preg_match('/[\\\\\/\x00-\x1f]/', $filename) || in_array($filename, ['.', '..'], true)) {
            throw new RuntimeException('Şəkil fayl adı yanlışdır.');
        }
        $url = config('services.legacy_credit_profiles.images_url').rawurlencode($filename);
        $response = Http::withoutRedirecting()->connectTimeout(10)->timeout(30)
            ->withOptions(['progress' => function ($total, $downloaded) {
                if ($total > 5 * 1024 * 1024 || $downloaded > 5 * 1024 * 1024) throw new RuntimeException('Şəkil 5 MB həddini keçir.');
            }])->get($url);
        $body = $response->body();
        if (!$response->successful() || strlen($body) > 5 * 1024 * 1024) throw new RuntimeException('Şəkil yüklənmədi.');
        $info = @getimagesizefromstring($body);
        $extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
        $extension = $extensions[$info['mime'] ?? ''] ?? null;
        if (!$extension) throw new RuntimeException('Fayl JPEG, PNG və ya WEBP şəkli deyil.');
        return ['body' => $body, 'extension' => $extension];
    }
}
