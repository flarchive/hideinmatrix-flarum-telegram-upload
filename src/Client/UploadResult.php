<?php

namespace HideInMatrix\TelegramUpload\Client;

final readonly class UploadResult
{
    public function __construct(public string $provider, public string $fileId, public string $url) {}
}
