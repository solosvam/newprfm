<?php

namespace App\Services\PriceList;

use PhpOffice\PhpSpreadsheet\IOFactory;
use RuntimeException;
use Throwable;

/** Excel / CSV faylını sətirlərə çevirir (.xls, .xlsx, .csv). Xanalar formatsız — rəqəm rəqəm kimi gəlir. */
class PriceListReader
{
    /** @return list<string> vərəq adları */
    public function sheets(string $path): array
    {
        return $this->load($path)->getSheetNames();
    }

    /**
     * @return list<list<mixed>> sətirlər; hər sətir sütunların dəyərləri (A = 0)
     */
    public function rows(string $path, int $sheet = 0, ?int $limit = null): array
    {
        $book = $this->load($path);
        if ($sheet < 0 || $sheet >= $book->getSheetCount()) {
            throw new RuntimeException('Faylda belə vərəq yoxdur.');
        }
        $rows = $book->getSheet($sheet)->toArray(null, true, false, false);

        return $limit ? array_slice($rows, 0, $limit) : $rows;
    }

    private function load(string $path)
    {
        try {
            $reader = IOFactory::createReaderForFile($path);
            $reader->setReadDataOnly(true);

            return $reader->load($path);
        } catch (Throwable $e) {
            throw new RuntimeException('Fayl oxunmadı: Excel (.xls, .xlsx) və ya CSV olmalıdır.', 0, $e);
        }
    }
}
