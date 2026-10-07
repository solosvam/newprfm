<?php
namespace App\Models\Customer;
use App\Models\City;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

class CustomerAddress extends Model {
    protected $guarded=[];
    protected function casts(): array
    {
        return ['is_default'=>'boolean'];
    }
    public function customer(){
        return $this->belongsTo(Customer::class);
    }
    public function cityRef()
    {
        return $this->belongsTo(City::class, 'city_id');
    }

    /**
     * Yeni ünvan formasının validasiya qaydaları (checkout, kredit ünvanı, CRM).
     * $when: 'required_if:address_mode,new' kimi şərt; boşdursa sahələr həmişə məcburidir.
     */
    public static function formRules(string $when = 'required', bool $withTitle = true): array
    {
        return array_filter([
            'title' => $withTitle ? [$when, 'nullable', 'string', 'max:50'] : null,
            'city_id' => [$when, 'nullable', 'integer', Rule::exists('cities', 'id')->where('active', true)],
            'address' => [$when, 'nullable', 'string', 'max:500'],
            'building' => ['nullable', 'string', 'max:50'],
            'entrance' => ['nullable', 'string', 'max:50'],
            'floor' => ['nullable', 'string', 'max:30'],
            'apartment' => ['nullable', 'string', 'max:30'],
            'address_note' => ['nullable', 'string', 'max:1000'],
            // xəritədə seçilmiş nöqtə (Google Maps) — Azərbaycan hüdudları təxminən
            'latitude' => ['nullable', 'numeric', 'between:38,42', 'required_with:longitude'],
            'longitude' => ['nullable', 'numeric', 'between:44,51', 'required_with:latitude'],
        ]);
    }

    /** Formdan gələn məlumatı cədvəl sütunlarına çevirir (şəhərin adı `city`-yə də yazılır) */
    public static function attributesFromForm(array $data, ?string $title = null): array
    {
        // koordinat yalnız formada varsa yazılır — göndərməyən forma mövcud nöqtəni silməsin
        $location = array_key_exists('latitude', $data) ? [
            'latitude' => $data['latitude'] !== null && $data['latitude'] !== '' ? round((float) $data['latitude'], 7) : null,
            'longitude' => ($data['longitude'] ?? null) !== null && $data['longitude'] !== '' ? round((float) $data['longitude'], 7) : null,
        ] : [];

        return $location + [
            'title' => $title ?? ($data['title'] ?? null),
            'city_id' => (int) $data['city_id'],
            'city' => City::whereKey($data['city_id'])->value('name'),
            'address' => $data['address'],
            'building' => $data['building'] ?? null,
            'entrance' => $data['entrance'] ?? null,
            'floor' => $data['floor'] ?? null,
            'apartment' => $data['apartment'] ?? null,
            'note' => $data['address_note'] ?? null,
        ];
    }

    public function hasLocation(): bool
    {
        return $this->latitude !== null && $this->longitude !== null;
    }

    /** Naviqasiya linkləri: koordinat varsa — nöqtə, yoxdursa — ünvan mətni ilə axtarış */
    public function mapsUrl(): string
    {
        return $this->hasLocation()
            ? 'https://www.google.com/maps/search/?api=1&query='.$this->latitude.','.$this->longitude
            : 'https://www.google.com/maps/search/?api=1&query='.rawurlencode($this->city.', '.$this->address);
    }

    public function directionsUrl(): string
    {
        return 'https://www.google.com/maps/dir/?api=1&destination='.($this->hasLocation()
            ? $this->latitude.','.$this->longitude
            : rawurlencode($this->city.', '.$this->address));
    }

    public function wazeUrl(): string
    {
        return $this->hasLocation()
            ? 'https://waze.com/ul?ll='.$this->latitude.','.$this->longitude.'&navigate=yes'
            : 'https://waze.com/ul?q='.rawurlencode($this->city.', '.$this->address).'&navigate=yes';
    }

    public function getLabelAttribute():string
    {
        return trim(($this->title ? $this->title.' — ' : '').$this->city.', '.$this->address);
    }
}
