<?php

namespace HideInMatrix\TelegramUpload\Tests\integration;

use Flarum\Http\UrlGenerator;
use Flarum\Testing\integration\TestCase;
use FoF\Upload\Adapters\Manager;
use FoF\Upload\File;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use HideInMatrix\TelegramUpload\Adapters\Telegram;
use HideInMatrix\TelegramUpload\Client\TgDiskClient;
use HideInMatrix\TelegramUpload\Configuration;

final class ExtensionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->extension('fof-upload', 'hideinmatrix-telegram-upload');
        $this->config('telegram-upload', ['base_url' => 'https://disk.example.com', 'chat_id' => '-100123456', 'api_key' => str_repeat('k', 32)]);
    }

    public function test_flarum_registers_the_adapter_and_preserves_legacy_urls(): void
    {
        $container = $this->app()->getContainer();
        self::assertInstanceOf(Telegram::class, $container->make(Manager::class)->instantiate('telegram'));
        self::assertSame('http://localhost/api/tinker-telegram/fixture', $container->make(UrlGenerator::class)->to('api')->route('tinker-telegram.file', ['uuid' => 'fixture']));
    }

    public function test_guest_request_still_requires_fof_download_permission(): void
    {
        $response = $this->send($this->request('GET', '/api/tinker-telegram/not-a-file'));
        self::assertSame(403, $response->getStatusCode());
    }

    public function test_missing_api_key_disables_upload_storage(): void
    {
        $this->config('telegram-upload.api_key', '');
        self::assertFalse($this->app()->getContainer()->make(Manager::class)->adapters()->get('telegram'));
    }

    public function test_api_key_is_not_in_the_public_forum_payload(): void
    {
        $response = $this->send($this->request('GET', '/api'));
        self::assertSame(200, $response->getStatusCode());
        self::assertStringNotContainsString(str_repeat('k', 32), (string) $response->getBody());
    }

    public function test_authorized_image_download_preserves_bytes_and_content_type(): void
    {
        $container = $this->app()->getContainer();
        $bytes = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aP1sAAAAASUVORK5CYII=');
        $container->instance(TgDiskClient::class, new TgDiskClient(
            new Client(['handler' => HandlerStack::create(new MockHandler([new Response(200, ['Content-Length' => (string) strlen($bytes)], $bytes)]))]),
            $container->make(Configuration::class)
        ));
        // Replace the downloader already created at service-provider boot.
        \FoF\Upload\Commands\DownloadHandler::prependDownloader($container->make(\HideInMatrix\TelegramUpload\Downloaders\TelegramDownloader::class));
        (new File())->forceFill([
            'uuid' => '00000000-0000-4000-8000-000000000001', 'base_name' => 'fixture.png',
            'size' => strlen($bytes), 'type' => 'image/png', 'actor_id' => 1,
            'upload_method' => 'telegram', 'remote_id' => 'test_file_id',
            'path' => 'telegram/test_file_id', 'url' => 'http://localhost/api/tinker-telegram/00000000-0000-4000-8000-000000000001',
            'tag' => $container->make(\FoF\Upload\Helpers\Util::class)->getTemplate('image-preview'), 'hidden' => false, 'shared' => false,
        ])->save();
        $response = $this->send($this->requestAsUser($this->request('GET', '/api/tinker-telegram/00000000-0000-4000-8000-000000000001'), 1));
        self::assertSame(200, $response->getStatusCode());
        self::assertSame('image/png', $response->getHeaderLine('Content-Type'));
        self::assertSame(hash('sha256', $bytes), hash('sha256', (string) $response->getBody()));
    }

    public function test_adapter_acknowledges_forum_only_deletion(): void
    {
        $adapter = $this->app()->getContainer()->make(Manager::class)->instantiate('telegram');
        self::assertTrue($adapter->delete(new File()));
    }
}
