<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Services\AdminMenuService;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class AdminMenuTest extends TestCase
{
    private function menuFor(array $abilities): array
    {
        Gate::before(fn ($user, $ability) => in_array($ability, $abilities, true));
        auth()->shouldUse('admin');
        auth('admin')->setUser(new User(['name' => 'Test']));

        $sales = collect(app(AdminMenuService::class)->items())->firstWhere('title', 'Satışlar');

        return array_map(fn ($i) => $i['title'] ?? '---', $sales['children'] ?? []);
    }

    public function test_separator_before_statistics_is_shown_with_permission(): void
    {
        $this->assertSame(['Sifarişlər', 'Asan sifarişlər', 'Kredit müraciətləri', 'Anbarlar', 'Price listlər', '---', 'Statistika'],
            $this->menuFor(['crm', 'credit.menu', 'statistics']));
    }

    public function test_trailing_separator_is_dropped(): void
    {
        $this->assertSame(['Sifarişlər', 'Asan sifarişlər', 'Anbarlar', 'Price listlər'], $this->menuFor(['crm']));
    }

    public function test_leading_separator_is_dropped(): void
    {
        $this->assertSame(['Statistika'], $this->menuFor(['statistics']));
    }

    public function test_search_pages_skip_separators(): void
    {
        $this->menuFor(['crm', 'statistics']);
        $labels = array_column(app(AdminMenuService::class)->searchPages(), 'label');
        $this->assertContains('Satışlar > Statistika', $labels);
        $this->assertNotContains('Satışlar > ', $labels);
    }
}
