<?php

namespace HideInMatrix\TelegramUpload\Processors;

use Flarum\Foundation\ValidationException;
use FoF\Upload\File;
use FoF\Upload\Helpers\Util;
use FoF\Upload\Processors\ImageProcessor;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class PreserveImageProcessor extends ImageProcessor
{
    public function process(File $file, UploadedFile $upload, string $mimeType): void
    {
        $configuration = resolve(Util::class)->getMimeConfiguration($mimeType);
        if (($configuration['adapter'] ?? '') !== 'telegram'
            || $this->settings->get('fof-upload.mustResize')
            || $this->settings->get('fof-upload.addsWatermarks')) {
            parent::process($file, $upload, $mimeType);

            return;
        }

        $dimensions = @getimagesize($upload->getRealPath());
        if (!$dimensions) {
            throw new ValidationException(['upload' => 'Invalid image content.']);
        }
        $file->image_width = $dimensions[0];
        $file->image_height = $dimensions[1];
    }
}
