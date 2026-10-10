<?php

namespace Tests\Feature;

use App\Support\ShortUrl;
use Tests\TestCase;

class ShortUrlTest extends TestCase
{
    public function test_links_use_main_domain_when_short_url_is_not_configured(): void
    {
        config(['app.short_url' => null]);

        $this->assertSame(route('pay.link', 'AbC123'), ShortUrl::route('pay.link', 'AbC123'));
        // Qısa domen ayarlanmayıbsa heç bir sorğu yönləndirilmir
        $this->get('http://paf.az/up')->assertOk();
    }

    public function test_links_use_short_domain_when_configured(): void
    {
        config(['app.short_url' => 'https://paf.az/', 'app.url' => 'https://parfumshop.az']);

        $this->assertSame('https://paf.az/p/AbC123', ShortUrl::route('pay.link', 'AbC123'));
        $this->assertSame('https://paf.az/r/RUFAT1', ShortUrl::route('referral.track', 'RUFAT1'));
        $this->assertSame('https://paf.az/w/tok123tok123', ShortUrl::route('warehouse.portal', ['token' => 'tok123tok123']));
    }

    public function test_short_domain_redirects_same_path_to_main_site(): void
    {
        config(['app.short_url' => 'https://paf.az', 'app.url' => 'https://parfumshop.az']);

        $this->get('https://paf.az/p/AbC123')->assertStatus(302)->assertRedirect('https://parfumshop.az/p/AbC123');
        $this->get('https://www.paf.az/w/tok123tok123?tab=selected')->assertRedirect('https://parfumshop.az/w/tok123tok123?tab=selected');
        $this->get('https://paf.az/')->assertRedirect('https://parfumshop.az/');
        $this->post('https://paf.az/p/AbC123')->assertNotFound();
    }

    public function test_main_domain_is_not_redirected(): void
    {
        config(['app.short_url' => 'https://paf.az', 'app.url' => 'https://parfumshop.az']);

        $this->get('https://parfumshop.az/up')->assertOk();
    }
}
