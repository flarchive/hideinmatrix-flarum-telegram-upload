<?php

namespace HideInMatrix\TelegramUpload\Tests\integration;

use Flarum\Testing\integration\TestCase;
use FoF\Upload\File;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use HideInMatrix\TelegramUpload\Client\TgDiskClient;
use HideInMatrix\TelegramUpload\Configuration;
use Laminas\Diactoros\UploadedFile;

final class UploadTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->extension('fof-upload', 'hideinmatrix-telegram-upload');
        $this->setting('hideinmatrix-telegram-upload.base_url', 'https://disk.example.com');
        $this->setting('hideinmatrix-telegram-upload.chat_id', '-100123456');
        $this->setting('hideinmatrix-telegram-upload.api_key', str_repeat('k', 32));
        $this->setting('fof-upload.mimeTypes', json_encode(['^image\\/(jpeg|png|gif)$' => ['adapter' => 'telegram', 'template' => 'image-preview']]));
        $this->setting('fof-upload.maxFileSize', 10240);
        $this->setting('fof-upload.mustResize', 0);
        $this->setting('fof-upload.addsWatermarks', 0);
    }

    public function test_png_passes_the_complete_upload_api_and_persists_a_file(): void
    {
        $file = $this->upload('test_file_id', 'telegram');
        self::assertSame('https://disk.example.com/file/test_file_id', $file->url);
        self::assertSame('telegram/test_file_id', $file->path);
    }

    public function test_switching_to_r2_and_another_site_preserves_the_telegram_url(): void
    {
        $telegram = $this->upload('test_file_id', 'telegram');
        $container = $this->app()->getContainer();
        $settings = $container->make(\Flarum\Settings\SettingsRepositoryInterface::class);
        $settings->set('hideinmatrix-telegram-upload.provider', 'r2');
        $settings->set('hideinmatrix-telegram-upload.base_url', 'https://new-disk.example.com');
        $settings->set('hideinmatrix-telegram-upload.chat_id', '');
        $container->forgetInstance(Configuration::class);
        $r2 = $this->upload('fixture-key.png', 'r2');
        self::assertSame('https://new-disk.example.com/r2/fixture-key.png', $r2->url);
        self::assertSame('r2/fixture-key.png', $r2->path);
        self::assertSame('https://disk.example.com/file/test_file_id', $telegram->refresh()->url);
        // Returning to Telegram must also leave the saved R2 URL unchanged.
        $settings->set('hideinmatrix-telegram-upload.provider', 'telegram');
        self::assertSame('https://new-disk.example.com/r2/fixture-key.png', $r2->refresh()->url);
    }

    private function upload(string $id, string $provider): File
    {
        $bytes = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aP1sAAAAASUVORK5CYII=');
        $container = $this->app()->getContainer();
        $history = [];
        $stack = HandlerStack::create(new MockHandler([new Response(200, [], json_encode([
            'code' => 200, 'data' => ['file_id' => $id, 'file_size' => strlen($bytes)],
        ]))]));
        $stack->push(Middleware::history($history));
        $container->instance(TgDiskClient::class, new TgDiskClient(new Client(['handler' => $stack]), $container->make(Configuration::class)));
        $stream = fopen('php://temp', 'w+b');
        fwrite($stream, $bytes);
        rewind($stream);
        $request = $this->request('POST', '/api/fof/upload')->withUploadedFiles([
            'files' => [new UploadedFile($stream, strlen($bytes), UPLOAD_ERR_OK, 'fixture.png', 'image/png')],
        ]);

        $response = $this->send($this->requestAsUser($request, 1));

        self::assertSame(200, $response->getStatusCode(), (string) $response->getBody());
        self::assertCount(1, $history);
        self::assertStringContainsString($bytes, (string) $history[0]['request']->getBody());
        self::assertStringEndsWith('/api/'.$provider.'/send', (string) $history[0]['request']->getUri());
        $file = File::query()->where('remote_id', $id)->firstOrFail();
        self::assertSame('telegram', $file->upload_method);
        self::assertSame($id, $file->remote_id);
        self::assertSame(strlen($bytes), $file->size);
        $attributes = json_decode((string) $response->getBody(), true)['data'][0]['attributes'];
        self::assertSame($file->url, $attributes['url']);
        self::assertStringContainsString('url='.$file->url, $attributes['bbcode']);

        return $file;
    }
}
