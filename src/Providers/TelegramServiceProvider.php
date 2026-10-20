<?php

namespace HideInMatrix\TelegramUpload\Providers;

use Flarum\Foundation\AbstractServiceProvider;
use Flarum\Foundation\Config;
use Flarum\Settings\SettingsRepositoryInterface;
use FoF\Upload\Commands\DownloadHandler;
use FoF\Upload\Processors\ImageProcessor;
use GuzzleHttp\Client;
use HideInMatrix\TelegramUpload\Client\TgDiskClient;
use HideInMatrix\TelegramUpload\Commands\DeleteFileHandler;
use HideInMatrix\TelegramUpload\Configuration;
use HideInMatrix\TelegramUpload\Downloaders\TelegramDownloader;
use HideInMatrix\TelegramUpload\Processors\PreserveImageProcessor;

final class TelegramServiceProvider extends AbstractServiceProvider
{
    public function register(): void
    {
        $this->container->bind(\FoF\Upload\Commands\DeleteFileHandler::class, DeleteFileHandler::class);
        $this->container->singleton(Configuration::class, function ($container) {
            $config = $container->make(Config::class);
            $values = $config['telegram-upload'] ?? [];

            $values = array_replace([
                'base_url' => getenv('TG_DISK_BASE_URL') ?: '',
                'chat_id' => getenv('TG_DISK_CHAT_ID') ?: '',
                'api_key' => getenv('TG_DISK_API_KEY') ?: '',
            ], is_array($values) ? $values : []);
            $settings = $container->make(SettingsRepositoryInterface::class);
            foreach (['upload_url', 'chat_id', 'api_key'] as $name) {
                $value = $settings->get('hideinmatrix-telegram-upload.'.$name);
                // A saved empty field deliberately disables that value; never revive an old secret.
                if ($value !== null) {
                    $values[$name] = $value;
                }
            }
            foreach (['base_url', 'provider'] as $name) {
                $value = $settings->get('hideinmatrix-telegram-upload.'.$name);
                if ($value !== null) {
                    $values[$name] = $value;
                    if ($name === 'base_url') {
                        unset($values['upload_url']);
                    }
                }
            }

            return new Configuration($values);
        });
        $this->container->singleton(TgDiskClient::class, function ($container) {
            return new TgDiskClient(new Client(), $container->make(Configuration::class));
        });
        $this->container->extend(ImageProcessor::class, function ($processor, $container) {
            return $container->make(PreserveImageProcessor::class);
        });
    }

    public function boot(): void
    {
        // Must run before FoF's catch-all DefaultDownloader.
        DownloadHandler::prependDownloader($this->container->make(TelegramDownloader::class));
    }
}
