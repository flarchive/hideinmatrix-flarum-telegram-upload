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
        $this->setting('hideinmatrix-telegram-upload.upload_url', 'https://disk.example.com/api/telegram/send');
        $this->setting('hideinmatrix-telegram-upload.chat_id', '-100123456');
        $this->setting('hideinmatrix-telegram-upload.api_key', str_repeat('k', 32));
        $this->setting('fof-upload.mimeTypes', json_encode(['^image\\/(jpeg|png|gif)$' => ['adapter' => 'telegram', 'template' => 'image-preview']]));
        $this->setting('fof-upload.maxFileSize', 10240);
        $this->setting('fof-upload.mustResize', 0);
        $this->setting('fof-upload.addsWatermarks', 0);
    }

    public function test_png_passes_the_complete_upload_api_and_persists_a_file(): void
    {
        $bytes = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aP1sAAAAASUVORK5CYII=');
        $container = $this->app()->getContainer();
        $history = [];
        $stack = HandlerStack::create(new MockHandler([new Response(200, [], json_encode([
            'code' => 200, 'data' => ['file_id' => 'test_file_id', 'file_size' => strlen($bytes)],
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
        $file = File::query()->firstOrFail();
        self::assertSame('telegram', $file->upload_method);
        self::assertSame('test_file_id', $file->remote_id);
        self::assertSame(strlen($bytes), $file->size);
    }
}
