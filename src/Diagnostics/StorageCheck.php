<?php

namespace HideInMatrix\TelegramUpload\Diagnostics;

final class StorageCheck
{
    /** @return array<string, array{ok: bool, exists: bool}> */
    public function inspect(string $forumRoot): array
    {
        $checks = [];
        foreach (['storage/tmp', 'storage/private-shared/files', 'public/assets'] as $relative) {
            $target = rtrim($forumRoot, '/').'/'.$relative;
            $existing = $target;
            while (!file_exists($existing) && !is_link($existing) && dirname($existing) !== $existing) {
                $existing = dirname($existing);
            }
            // Existing directories must be writable; missing ones need a writable,
            // searchable parent. Do not create directories or change permissions.
            $checks[$relative] = [
                'ok' => is_dir($existing) && is_writable($existing)
                    && (PHP_OS_FAMILY === 'Windows' || is_executable($existing)),
                'exists' => is_dir($target),
            ];
        }

        return $checks;
    }
}
