<?php

namespace Tests\Unit;

use App\Services\IdCard\IdCardParser;
use PHPUnit\Framework\TestCase;

/** Uydurma məlumatlar — düzülüş real kartlardakı kimidir (Vision fullTextAnnotation sətir-sətir) */
class IdCardParserTest extends TestCase
{
    private function parse(string $text): array
    {
        return (new IdCardParser())->parse($text);
    }

    public function test_new_card_aa(): void
    {
        $r = $this->parse(<<<'TXT'
            AZƏRBAYCAN RESPUBLİKASI - REPUBLIC OF AZERBAIJAN
            şəxsiyyət vəsiqəsi - IDENTITY CARD
            SOYADI/SURNAME
            TESTOVA
            ADI/GIVEN NAME
            GÜLNAR
            ATASININ ADI/PATRONYMIC
            İLHAM QIZI
            CİNSİ/SEX VƏTƏNDAŞLIĞI/NATIONALITY DOĞULDUĞU TARİX/DATE OF BIRTH
            Q/F AZƏRBAYCAN 01.02.1990
            VƏSİQƏNİN NÖMRƏSİ/CARD NO FƏRDİ İDENTİFİKASİYA NÖMRƏSİ/PERSONAL NO
            AA1234567 5XYZ12A
            ETİBARLILIQ MÜDDƏTİ/DATE OF EXPIRY
            01.01.2030
            TXT);

        $this->assertSame('new', $r['type']);
        $this->assertSame('AA', $r['series']);
        $this->assertSame('1234567', $r['number']);
        $this->assertSame('5XYZ12A', $r['fin']);
        $this->assertSame('Testova', $r['surname']);
        $this->assertSame('Gülnar', $r['name']);
        $this->assertSame('İlham', $r['father_name']);
    }

    public function test_new_card_ab_number_in_corner_and_values_on_separate_lines(): void
    {
        $r = $this->parse(<<<'TXT'
            AZƏRBAYCAN RESPUBLİKASI - REPUBLIC OF AZERBAIJAN
            şəxsiyyət vəsiqəsi - IDENTITY CARD
            AB0001234
            SOYADI/SURNAME
            NƏMƏTOV
            ADI/GIVEN NAME
            İSMAYIL
            ATASININ ADI/PATRONYMIC
            ƏLİHÜSEYN OĞLU
            DOĞULDUĞU TARİX/DATE OF BIRTH
            05.05.1980
            FƏRDİ İDENTİFİKASİYA NÖMRƏSİ/PERSONAL NO
            CAN
            123456
            7QWE3RT
            TXT);

        $this->assertSame('AB', $r['series']);
        $this->assertSame('0001234', $r['number']);
        $this->assertSame('7QWE3RT', $r['fin']);
        $this->assertSame('Nəmətov', $r['surname']);
        $this->assertSame('İsmayıl', $r['name']);
        $this->assertSame('Əlihüseyn', $r['father_name']);
    }

    public function test_spaced_series_and_russian_patronymic(): void
    {
        $r = $this->parse(<<<'TXT'
            IDENTITY CARD
            ATASININ ADI/PATRONYMIC
            RAMİZOVİÇ
            VƏSİQƏNİN NÖMRƏSİ/CARD NO
            A A 7654321
            FƏRDİ İDENTİFİKASİYA NÖMRƏSİ/PERSONAL NO
            9ABC1D2
            TXT);

        $this->assertSame(['AA', '7654321', '9ABC1D2'], [$r['series'], $r['number'], $r['fin']]);
        $this->assertSame('Ramiz', $r['father_name']);
    }

    public function test_old_card_aze_with_mrz(): void
    {
        $r = $this->parse(<<<'TXT'
            AZƏRBAYCAN RESPUBLİKASI
            VƏTƏNDAŞININ ŞƏXSİYYƏT VƏSİQƏSİ
            Seriya AZE № 12345678
            Soyadı
            QULİYEV
            Adı
            ELVİN
            Atasının adı
            RAUF OĞLU
            Doğulduğu yer və tarix
            AZƏRBAYCAN,BAKI şəh.
            I<AZEQULIYEV<<ELVIN<<<<<<<<<<<<
            12345678<6AZE8409207M1909203 4ABCD5E3
            TXT);

        $this->assertSame('old', $r['type']);
        $this->assertSame(['AZE', '12345678'], [$r['series'], $r['number']]);
        $this->assertSame('4ABCD5E', $r['fin']);
        $this->assertSame('Quliyev', $r['surname']);
        $this->assertSame('Elvin', $r['name']);
        $this->assertSame('Rauf', $r['father_name']);
    }

    public function test_old_card_number_only_in_mrz(): void
    {
        $r = $this->parse("Seriya AZE №\nSoyadı\nQULİYEV\n12345678<6AZE8409207M1909203 4ABCD5E3");
        $this->assertSame(['AZE', '12345678', '4ABCD5E'], [$r['series'], $r['number'], $r['fin']]);
    }

    public function test_not_an_id_card(): void
    {
        $r = $this->parse("Chanel Bleu\n100 ml\nEAU DE PARFUM");
        $this->assertSame([null, null, null, null], [$r['type'], $r['series'], $r['number'], $r['fin']]);
    }

    public function test_key_normalizes_azerbaijani_letters(): void
    {
        $this->assertSame('FERDI IDENTIFIKASIYA NOMRESI/PERSONAL NO', (new IdCardParser())->key('Fərdi identifikasiya nömrəsi/Personal No'));
    }
}
