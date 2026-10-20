<?php

namespace HideInMatrix\TelegramUpload\Commands;

use FoF\Upload\File;

final class DeleteFileHandler extends \FoF\Upload\Commands\DeleteFileHandler
{
    protected function deleteFileViaAdaptor(File $file): bool
    {
        if ($file->upload_method === 'telegram') {
            // Only acknowledge local record cleanup. No upload credentials, MIME
            // mapping or network request is needed to remove Telegram media metadata.
            // The parent handler retains FoF's permission checks and DB deletion.
            return true;
        }

        // FoF 2.0.1 allowed File|bool; 2.0.2 narrows the result to bool.
        // Both versions treat only false as failure.
        return parent::deleteFileViaAdaptor($file) !== false;
    }
}
