<?php

namespace HideInMatrix\TelegramUpload\Tests\unit;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use HideInMatrix\TelegramUpload\Client\TgDiskClient;
use HideInMatrix\TelegramUpload\Configuration;
use HideInMatrix\TelegramUpload\Exceptions\ApiKeyRejectedException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class TgDiskClientTest extends TestCase
{
    private function client(MockHandler $mock, array &$history): TgDiskClient
    {
        $stack = HandlerStack::create($mock);
        $stack->push(Middleware::history($history));

        return new TgDiskClient(new Client(['handler' => $stack]), new Configuration([
            'base_url' => 'https://disk.example.com', 'chat_id' => '-100123456',
            'api_key' => str_repeat('k', 32),
        ]));
    }

    public function test_upload_sends_the_original_bytes_as_a_document(): void
    {
        $history = [];
        $mock = new MockHandler([new Response(200, [], '{"code":200,"data":{"file_id":"test_file_id","file_size":7}}')]);
        $bytes = "GIF\0\xff\x01x";
        $client = $this->client($mock, $history);
        $result = $client->upload('fixture', $bytes, strlen($bytes));
        self::assertSame('test_file_id', $result->fileId);
        self::assertSame('https://disk.example.com/file/test_file_id', $result->url);
        self::assertSame('telegram', $result->provider);
        $request = $history[0]['request'];
        self::assertSame('https://disk.example.com/api/telegram/send', (string) $request->getUri());
        self::assertSame(str_repeat('k', 32), $request->getHeaderLine('X-API-Key'));
        self::assertStringNotContainsString(str_repeat('k', 32), (string) $request->getUri());
        self::assertStringNotContainsString(str_repeat('k', 32), (string) $request->getBody());
        self::assertStringContainsString('filename="fixture.bin"', (string) $request->getBody());
        self::assertStringContainsString($bytes, (string) $request->getBody());
        self::assertFalse($history[0]['options']['allow_redirects']);
    }

    public function test_r2_upload_preserves_filename_and_mime_without_a_channel(): void
    {
        $history = [];
        $stack = HandlerStack::create(new MockHandler([new Response(200, [], '{"code":200,"data":{"file_id":"fixture-key.avif","file_size":3}}')]));
        $stack->push(Middleware::history($history));
        $client = new TgDiskClient(new Client(['handler' => $stack]), new Configuration([
            'base_url' => 'https://disk.example.com', 'provider' => 'r2', 'api_key' => str_repeat('k', 32),
        ]));
        $result = $client->upload('fixture', 'avf', 3, 'original.avif', 'image/avif');
        self::assertSame('https://disk.example.com/r2/fixture-key.avif', $result->url);
        self::assertSame('r2', $result->provider);
        self::assertSame('https://disk.example.com/api/r2/send', (string) $history[0]['request']->getUri());
        self::assertSame(str_repeat('k', 32), $history[0]['request']->getHeaderLine('X-API-Key'));
        $body = (string) $history[0]['request']->getBody();
        self::assertStringContainsString('filename="original.avif"', $body);
        self::assertStringContainsString('Content-Type: image/avif', $body);
        self::assertStringNotContainsString('chatId', $body);
    }

    #[DataProvider('authenticationStatuses')]
    public function test_authentication_errors_do_not_expose_upstream_details(int $status): void
    {
        $history = [];
        $mock = new MockHandler([new Response($status, [], 'upstream-private-detail-'.str_repeat('k', 32))]);
        try {
            $this->client($mock, $history)->upload('fixture', 'x', 1);
            self::fail('Authentication error was accepted');
        } catch (ApiKeyRejectedException $error) {
            self::assertSame($status, $error->getCode());
            self::assertStringNotContainsString('upstream-private-detail', $error->getMessage());
            self::assertStringNotContainsString(str_repeat('k', 32), $error->getMessage());
            self::assertCount(1, $history);
        }
    }

    public static function authenticationStatuses(): array
    {
        return [[401], [403]];
    }

    public function test_public_file_reads_do_not_receive_the_private_api_key(): void
    {
        $history = [];
        $client = $this->client(new MockHandler([new Response(200, [], 'x')]), $history);
        self::assertSame('x', (string) $client->download('test_file_id')->getBody());
        self::assertFalse($history[0]['request']->hasHeader('X-API-Key'));
        self::assertFalse($history[0]['options']['allow_redirects']);
    }

    public function test_redirects_do_not_forward_the_api_key_to_another_host(): void
    {
        $history = [];
        $mock = new MockHandler([new Response(302, ['Location' => 'https://other.example.com/upload']), new Response(200)]);
        try {
            $this->client($mock, $history)->upload('fixture', 'x', 1);
            self::fail('Upload redirect was accepted');
        } catch (\RuntimeException $error) {
            self::assertCount(1, $history);
            self::assertCount(1, $mock);
            self::assertStringContainsString('HTTP 302', $error->getMessage());
        }
    }

    public function test_missing_key_is_rejected_without_an_unauthenticated_request(): void
    {
        $history = [];
        $stack = HandlerStack::create(new MockHandler([new Response(200)]));
        $stack->push(Middleware::history($history));
        $client = new TgDiskClient(new Client(['handler' => $stack]), new Configuration(['base_url' => 'https://disk.example.com', 'chat_id' => '-100123456']));
        try {
            $client->upload('fixture', 'x', 1);
            self::fail('Missing key was accepted');
        } catch (\InvalidArgumentException) {
            self::assertSame([], $history);
        }
    }

    #[DataProvider('badResponses')]
    public function test_failed_uploads_do_not_return_a_successful_file_id(string $json): void
    {
        $history = [];
        $client = $this->client(new MockHandler([new Response(200, [], $json)]), $history);
        $this->expectException(\Throwable::class);
        $client->upload('fixture', 'x', 1);
    }

    public static function badResponses(): array
    {
        return [
            ['{"code":500,"data":null}'],
            ['{"code":200,"data":{"file_id":"../../escape","file_size":1}}'],
            ['{"code":200,"data":{"file_id":"test_file_id","file_size":2}}'],
            ['not-json'],
        ];
    }

    public function test_oversized_upload_is_rejected_before_any_request(): void
    {
        $history = [];
        $mock = new MockHandler([new Response(200)]);
        try {
            $this->client($mock, $history)->upload('fixture', 'x', Configuration::MAX_BYTES + 1);
            self::fail('Oversize was accepted');
        } catch (\RuntimeException) {
            self::assertSame([], $history);
            self::assertCount(1, $mock);
        }
    }
}
