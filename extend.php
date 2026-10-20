<?php

/*
 * This file is part of hideinmatrix/flarum-telegram-upload.
 * Copyright (c) 2026 HideInMatrix.
 * See LICENSE.md for license information.
 */

namespace HideInMatrix\TelegramUpload;

use Flarum\Extend;
use FoF\Upload\Events\Adapter\Collecting;
use FoF\Upload\Events\Adapter\Instantiate;

return [
    (new Extend\ServiceProvider())->register(Providers\TelegramServiceProvider::class),
    (new Extend\Event())
        ->listen(Collecting::class, Listeners\CollectAdapters::class)
        ->listen(Instantiate::class, Listeners\InstantiateAdapter::class)
        ->listen(\Flarum\Settings\Event\Saving::class, Listeners\ValidateSettings::class),
    (new Extend\Frontend('admin'))->js(__DIR__.'/js/dist/admin.js'),
    // Compatibility with URLs issued by the original site-local integration.
    (new Extend\Routes('api'))->get('/tinker-telegram/{uuid}', 'tinker-telegram.file', Api\Controllers\FileController::class),
    new Extend\Locales(__DIR__.'/locale'),
];
