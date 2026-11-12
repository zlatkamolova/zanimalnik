<?php

namespace App\Service;

use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\ImageManager;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Quest covers are resized once, right after upload (no on-the-fly image cache on shared hosting):
 *  - the original is scaled down in place to at most 1600×1600 px;
 *  - a small WebP copy (max 640 px wide) is written next to it for cards: "<name>-640.webp".
 */
final class CoverImageProcessor
{
    public const MAX_SIZE = 1600;
    public const CARD_WIDTH = 640;
    private const QUALITY = 82;

    public function __construct(
        #[Autowire('%app.quest_cover_dir%')] private readonly string $coverDir,
        private readonly LoggerInterface $logger,
        private readonly Filesystem $filesystem = new Filesystem(),
    ) {
    }

    /** File name of the card-sized variant of a cover. */
    public static function cardFileName(string $fileName): string
    {
        return pathinfo($fileName, \PATHINFO_FILENAME).'-'.self::CARD_WIDTH.'.webp';
    }

    /** Idempotent: does nothing when the card variant already exists. */
    public function process(?string $fileName): void
    {
        if (!$fileName) {
            return;
        }

        $source = $this->coverDir.'/'.$fileName;
        $card = $this->coverDir.'/'.self::cardFileName($fileName);
        if (!is_file($source) || is_file($card)) {
            return;
        }

        try {
            $manager = ImageManager::usingDriver(GdDriver::class);

            $image = $manager->decodePath($source);
            if ($image->width() > self::MAX_SIZE || $image->height() > self::MAX_SIZE) {
                $image->scaleDown(self::MAX_SIZE, self::MAX_SIZE)->save($source, quality: self::QUALITY);
            }

            $manager->decodePath($source)
                ->scaleDown(width: self::CARD_WIDTH)
                ->save($card, quality: self::QUALITY);
        } catch (\Throwable $e) {
            // A broken image must not break saving the quest; the original is still shown.
            $this->logger->error('Cover image processing failed for {file}: {message}', [
                'file' => $fileName,
                'message' => $e->getMessage(),
            ]);
        }
    }

    /** Removes the card variant (the original is deleted by the upload field itself). */
    public function removeVariants(?string $fileName): void
    {
        if ($fileName) {
            $this->filesystem->remove($this->coverDir.'/'.self::cardFileName($fileName));
        }
    }
}
