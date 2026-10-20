<?php

namespace HideInMatrix\TelegramUpload\Tests\unit;

use FoF\Upload\File;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use HideInMatrix\TelegramUpload\Client\TgDiskClient;
use HideInMatrix\TelegramUpload\Configuration;
use HideInMatrix\TelegramUpload\Downloaders\TelegramDownloader;
use PHPUnit\Framework\TestCase;

final class TelegramDownloaderTest extends TestCase
{
    private function downloader(Response $response): TelegramDownloader
    {
        return new TelegramDownloader(new TgDiskClient(
            new Client(['handler' => HandlerStack::create(new MockHandler([$response]))]),
            new Configuration(['base_url' => 'https://disk.example.com', 'chat_id' => '-100123456'])
        ));
    }

    private function file(): File
    {
        return (new File())->forceFill([
            'remote_id' => 'test_file_id', 'base_name' => "test\r\nname.zip", 'size' => 3, 'upload_method' => 'telegram',
        ]);
    }

    public function test_successful_download_preserves_bytes_and_uses_private_cache_headers(): void
    {
        $response = $this->downloader(new Response(200, ['Content-Length' => '3'], 'zip'))->download($this->file());
        self::assertSame('zip', (string) $response->getBody());
        self::assertSame('application/octet-stream', $response->getHeaderLine('Content-Type'));
        self::assertSame('private, no-store', $response->getHeaderLine('Cache-Control'));
        self::assertSame('nosniff', $response->getHeaderLine('X-Content-Type-Options'));
        self::assertStringNotContainsString("\r", $response->getHeaderLine('Content-Disposition'));
        self::assertStringNotContainsString("\n", $response->getHeaderLine('Content-Disposition'));
    }

    public function test_size_mismatch_is_not_delivered_as_a_valid_attachment(): void
    {
        self::assertSame(502, $this->downloader(new Response(200, ['Content-Length' => '10'], 'bad'))->download($this->file())->getStatusCode());
    }

    public function test_remote_not_found_is_preserved(): void
    {
        self::assertSame(404, $this->downloader(new Response(404))->download($this->file())->getStatusCode());
    }

    public function test_invalid_file_id_does_not_become_a_proxy_url(): void
    {
        $file = $this->file();
        $file->remote_id = '../secret';
        self::assertSame(404, $this->downloader(new Response(200))->download($file)->getStatusCode());
    }
}
