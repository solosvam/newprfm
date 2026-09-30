<?php

namespace Tests\Feature\Credit;

use App\Models\Customer\Customer;
use App\Services\IdCard\GoogleVisionOcr;
use Illuminate\Http\UploadedFile;
use Mockery;
use Tests\TestCase;

class CreditProfileOcrTest extends TestCase
{
    private const CARD = "IDENTITY CARD\nSOYADI/SURNAME\nTESTOVA\nADI/GIVEN NAME\nGÜLNAR\nATASININ ADI/PATRONYMIC\nİLHAM QIZI\n"
        ."VƏSİQƏNİN NÖMRƏSİ/CARD NO FƏRDİ İDENTİFİKASİYA NÖMRƏSİ/PERSONAL NO\nAA1234567 5XYZ12A";

    private function customer(string $name = 'Gulnar', string $surname = 'Testova'): Customer
    {
        return (new Customer())->forceFill(['id' => 5, 'name' => $name, 'surname' => $surname]);
    }

    private function vision(?string $text, bool $configured = true): void
    {
        $mock = Mockery::mock(GoogleVisionOcr::class);
        $mock->shouldReceive('isConfigured')->andReturn($configured);
        if ($text !== null) {
            $mock->shouldReceive('text')->once()->andReturn($text);
        }
        $this->app->instance(GoogleVisionOcr::class, $mock);
    }

    private function sendCard(): \Illuminate\Testing\TestResponse
    {
        return $this->postJson('/profile/credit/ocr', ['image' => UploadedFile::fake()->image('card.jpg', 900, 570)]);
    }

    public function test_guest_cannot_use_it(): void
    {
        $this->sendCard()->assertUnauthorized();
    }

    public function test_fills_fields_and_matches_name(): void
    {
        $this->vision(self::CARD);
        $this->actingAs($this->customer())->sendCard()->assertOk()
            ->assertJsonPath('fields.father_name', 'İlham')
            ->assertJsonPath('fields.fin', '5XYZ12A')
            ->assertJsonPath('fields.id_card_series', 'AA')
            ->assertJsonPath('fields.id_card_number', '1234567')
            ->assertJsonPath('is_id_card', true)
            ->assertJsonPath('name_match', true); // "Gulnar" ~ "GÜLNAR"
    }

    public function test_name_mismatch_is_reported_not_blocked(): void
    {
        $this->vision(self::CARD);
        $this->actingAs($this->customer('Rəşad', 'Əliyev'))->sendCard()->assertOk()
            ->assertJsonPath('name_match', false)
            ->assertJsonPath('card_name', 'Gülnar Testova')
            ->assertJsonPath('fields.fin', '5XYZ12A');
    }

    public function test_not_an_id_card(): void
    {
        $this->vision("Chanel Bleu\n100 ml");
        $this->actingAs($this->customer())->sendCard()->assertOk()
            ->assertJsonPath('is_id_card', false)
            ->assertJsonPath('fields', []);
    }

    public function test_unavailable_without_key(): void
    {
        $this->vision(null, false);
        $this->actingAs($this->customer())->sendCard()->assertStatus(422); // 5xx yox — Cloudflare udur
    }

    public function test_vision_error_returns_readable_json(): void
    {
        $mock = Mockery::mock(GoogleVisionOcr::class);
        $mock->shouldReceive('isConfigured')->andReturn(true);
        $mock->shouldReceive('text')->andThrow(new \RuntimeException('PERMISSION_DENIED'));
        $this->app->instance(GoogleVisionOcr::class, $mock);
        config(['app.debug' => true]);

        $this->actingAs($this->customer())->sendCard()->assertStatus(422)
            ->assertJsonPath('message', __('credit_ocr_failed'))
            ->assertJsonPath('debug', 'RuntimeException: PERMISSION_DENIED');

        config(['app.debug' => false]);
        $this->actingAs($this->customer())->sendCard()->assertStatus(422)->assertJsonMissingPath('debug');
    }

    public function test_image_is_required(): void
    {
        $this->vision(null);
        $this->actingAs($this->customer())->postJson('/profile/credit/ocr', [])->assertStatus(422);
    }
}
