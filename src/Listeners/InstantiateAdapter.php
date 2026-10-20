<?php

namespace HideInMatrix\TelegramUpload\Listeners;

use FoF\Upload\Events\Adapter\Instantiate;
use HideInMatrix\TelegramUpload\Adapters\Telegram;

final class InstantiateAdapter
{
    public function __construct(private readonly Telegram $adapter) {}

    public function handle(Instantiate $event): ?Telegram
    {
        return $event->adapter === 'telegram' ? $this->adapter : null;
    }
}
