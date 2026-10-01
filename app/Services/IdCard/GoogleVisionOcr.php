<?php

namespace App\Services\IdCard;

use Google\Cloud\Vision\V1\AnnotateImageRequest;
use Google\Cloud\Vision\V1\BatchAnnotateImagesRequest;
use Google\Cloud\Vision\V1\Client\ImageAnnotatorClient;
use Google\Cloud\Vision\V1\Feature;
use Google\Cloud\Vision\V1\Feature\Type;
use Google\Cloud\Vision\V1\Image;
use Google\Cloud\Vision\V1\ImageContext;
use RuntimeException;

/**
 * Google Cloud Vision — sənəd mətni (DOCUMENT_TEXT_DETECTION) və ətir şəkli (TEXT_DETECTION + WEB_DETECTION).
 * Açar: .env GOOGLE_VISION_KEY_PATH (servis hesabının JSON faylı, storage/app altında, git-ə düşmür).
 * Transport REST — serverdə grpc genişlənməsi lazım deyil.
 */
class GoogleVisionOcr
{
    public function isConfigured(): bool
    {
        $path = $this->keyPath();

        return $path !== null && is_file($path) && is_readable($path);
    }

    /** Şəkildəki bütün mətn (sətirlər \n ilə) */
    public function text(string $imageBytes): string
    {
        if (!$this->isConfigured()) {
            throw new RuntimeException('Google Vision açarı tapılmadı (GOOGLE_VISION_KEY_PATH).');
        }

        $client = new ImageAnnotatorClient(['credentials' => $this->keyPath(), 'transport' => 'rest']);
        try {
            $request = (new AnnotateImageRequest())
                ->setImage((new Image())->setContent($imageBytes))
                ->setFeatures([(new Feature())->setType(Type::DOCUMENT_TEXT_DETECTION)])
                ->setImageContext((new ImageContext())->setLanguageHints(['az', 'en']));
            $response = $client->batchAnnotateImages((new BatchAnnotateImagesRequest())->setRequests([$request]))
                ->getResponses()[0];
        } finally {
            $client->close();
        }

        if ($response->hasError() && $response->getError()->getCode() !== 0) {
            throw new RuntimeException('Google Vision: '.$response->getError()->getMessage());
        }

        return (string) $response->getFullTextAnnotation()?->getText();
    }

    /**
     * Ətir şəkli (WhatsApp-da müştərinin göndərdiyi): bir sorğuda iki xüsusiyyət.
     *  - TEXT_DETECTION — şəkildəki yazı (skrinşot, şüşə üzərindəki ad);
     *  - WEB_DETECTION — internetdəki oxşar şəkillərə görə təxmin ("dior sauvage elixir") və varlıqlar (Dior, Sauvage…).
     *
     * @return array{text: string, labels: string[], entities: string[]}
     */
    public function productHints(string $imageBytes): array
    {
        if (!$this->isConfigured()) {
            throw new RuntimeException('Google Vision açarı tapılmadı (GOOGLE_VISION_KEY_PATH).');
        }

        $client = new ImageAnnotatorClient(['credentials' => $this->keyPath(), 'transport' => 'rest']);
        try {
            $request = (new AnnotateImageRequest())
                ->setImage((new Image())->setContent($imageBytes))
                ->setFeatures([
                    (new Feature())->setType(Type::TEXT_DETECTION),
                    (new Feature())->setType(Type::WEB_DETECTION)->setMaxResults(10),
                ]);
            $response = $client->batchAnnotateImages((new BatchAnnotateImagesRequest())->setRequests([$request]))
                ->getResponses()[0];
        } finally {
            $client->close();
        }

        if ($response->hasError() && $response->getError()->getCode() !== 0) {
            throw new RuntimeException('Google Vision: '.$response->getError()->getMessage());
        }

        $web = $response->getWebDetection();
        $labels = [];
        $entities = [];
        if ($web) {
            foreach ($web->getBestGuessLabels() as $label) {
                $labels[] = (string) $label->getLabel();
            }
            foreach ($web->getWebEntities() as $entity) {
                // zəif təxminlər ("Bottle", "Glass") çox vaxt aşağı ballı olur
                if ($entity->getDescription() !== '' && $entity->getScore() >= 0.3) {
                    $entities[] = (string) $entity->getDescription();
                }
            }
        }

        return [
            'text' => (string) $response->getFullTextAnnotation()?->getText(),
            'labels' => array_values(array_filter($labels)),
            'entities' => array_values(array_unique($entities)),
        ];
    }

    private function keyPath(): ?string
    {
        $path = config('services.google_vision.key_path');
        if (!$path) {
            return null;
        }

        return str_starts_with($path, '/') ? $path : base_path($path);
    }
}
