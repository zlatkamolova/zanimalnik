<?php

namespace App\Service;

use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\ImageManager;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/** Meme images are scaled down once, right after upload, to at most 1200×1200 px (in place). */
final class JokeImageProcessor
{
    public const MAX_SIZE = 1200;
    private const QUALITY = 82;

    public function __construct(
        #[Autowire('%app.joke_image_dir%')] private readonly string $imageDir,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function process(?string $fileName): void
    {
        $file = $this->imageDir.'/'.$fileName;
        if (!$fileName || !is_file($file)) {
            return;
        }

        try {
            $image = ImageManager::usingDriver(GdDriver::class)->decodePath($file);
            if ($image->width() > self::MAX_SIZE || $image->height() > self::MAX_SIZE) {
                $image->scaleDown(self::MAX_SIZE, self::MAX_SIZE)->save($file, quality: self::QUALITY);
            }
        } catch (\Throwable $e) {
            // A broken image must not break saving; the original is still shown.
            $this->logger->error('Joke image processing failed for {file}: {message}', [
                'file' => $fileName,
                'message' => $e->getMessage(),
            ]);
        }
    }
}
