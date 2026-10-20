<?php

namespace HideInMatrix\TelegramUpload\Listeners;

use FoF\Upload\Events\Adapter\Collecting;
use HideInMatrix\TelegramUpload\Configuration;

final class CollectAdapters
{
    public function __construct(private readonly Configuration $configuration) {}

    public function handle(Collecting $event): void
    {
        $event->adapters->put('telegram', $this->configuration->isConfigured());
    }
}
