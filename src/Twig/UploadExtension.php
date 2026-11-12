<?php

namespace App\Twig;

use App\Entity\Quest;
use App\Service\CoverImageProcessor;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Twig\Attribute\AsTwigFunction;

/** URLs of uploaded quest files. Paths are relative to the web root (public_html). */
final class UploadExtension
{
    public const COVER_PREFIX = '/uploads/quests/covers/';
    public const PDF_PREFIX = '/uploads/quests/pdf/';

    public function __construct(
        #[Autowire('%app.quest_cover_dir%')] private readonly string $coverDir,
    ) {
    }

    #[AsTwigFunction('quest_pdf_url')]
    public function pdfUrl(Quest $quest): ?string
    {
        return $quest->getPdfPath() ? self::PDF_PREFIX.rawurlencode($quest->getPdfPath()) : null;
    }

    /** $size: "full" (max 1600 px) or "card" (640 px WebP, falls back to full if missing). */
    #[AsTwigFunction('quest_cover_url')]
    public function coverUrl(Quest $quest, string $size = 'full'): ?string
    {
        $file = $quest->getCoverImagePath();
        if (!$file) {
            return null;
        }

        if ('card' === $size) {
            $card = CoverImageProcessor::cardFileName($file);
            if (is_file($this->coverDir.'/'.$card)) {
                return self::COVER_PREFIX.rawurlencode($card);
            }
        }

        return self::COVER_PREFIX.rawurlencode($file);
    }
}
