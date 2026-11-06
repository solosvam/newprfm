<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Admin terminologiyası: "Anbar" → "Depo". Bazadakı sistem mətnləri (status adları, maliyyə kateqoriyaları,
 * SMS şablonlarının adları, icazə təsvirləri). İstifadəçinin yazdığı adlara (depolar, hesablar) toxunulmur.
 */
return new class extends Migration {
    private const COLUMNS = [
        'order_statuses' => ['name_az'],
        'finance_categories' => ['name'],
        'sms_templates' => ['name'],
        'permissions' => ['description'],
    ];

    // uzun formalar əvvəl
    private const FORMS = [
        'anbarlardan' => 'depolardan', 'anbarlarda' => 'depolarda', 'anbarlarla' => 'depolarla',
        'anbarların' => 'depoların', 'anbarlara' => 'depolara', 'anbarları' => 'depoları', 'anbarlar' => 'depolar',
        'anbardan' => 'depodan', 'anbarda' => 'depoda', 'anbarla' => 'depoyla', 'anbarın' => 'deponun',
        'anbara' => 'depoya', 'anbarı' => 'deponu', 'anbar' => 'depo',
    ];

    public function up(): void
    {
        $this->apply(self::FORMS);
    }

    public function down(): void
    {
        $this->apply(array_flip(array_reverse(self::FORMS, true)));
    }

    private function apply(array $forms): void
    {
        $pattern = '/(?<![\p{L}])('.implode('|', array_map('preg_quote', array_keys($forms))).')(?![\p{L}])/iu';
        foreach (self::COLUMNS as $table => $columns) {
            foreach ($columns as $column) {
                if (!Schema::hasTable($table) || !Schema::hasColumn($table, $column)) {
                    continue;
                }
                foreach (DB::table($table)->where($column, 'like', '%'.(array_key_first($forms) === 'anbarlardan' ? 'nbar' : 'epo').'%')->get(['id', $column]) as $row) {
                    $new = preg_replace_callback($pattern, function ($m) use ($forms) {
                        $word = $m[1];
                        $lower = mb_strtolower(mb_substr($word, 0, 1)).mb_substr($word, 1);
                        $to = $forms[$lower] ?? $word;

                        return mb_substr($word, 0, 1) === mb_strtoupper(mb_substr($word, 0, 1)) ? mb_strtoupper(mb_substr($to, 0, 1)).mb_substr($to, 1) : $to;
                    }, $row->$column);
                    if ($new !== $row->$column) {
                        DB::table($table)->where('id', $row->id)->update([$column => $new]);
                    }
                }
            }
        }
    }
};
