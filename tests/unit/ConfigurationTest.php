<?php

namespace HideInMatrix\TelegramUpload\Tests\unit;

use HideInMatrix\TelegramUpload\Configuration;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ConfigurationTest extends TestCase
{
    public function test_missing_configuration_disables_the_adapter(): void
    {
        self::assertFalse((new Configuration())->isConfigured());
    }

    public function test_valid_configuration_normalizes_the_base_url(): void
    {
        $configuration = new Configuration(['base_url' => 'https://disk.example.com/', 'chat_id' => '-100123456', 'api_key' => str_repeat('k', 32)]);
        self::assertTrue($configuration->isConfigured());
        self::assertSame('https://disk.example.com', $configuration->baseUrl());
    }

    #[DataProvider('invalidUrls')]
    public function test_invalid_base_urls_disable_the_adapter(string $url): void
    {
        self::assertFalse((new Configuration(['base_url' => $url, 'chat_id' => '-100123456', 'api_key' => str_repeat('k', 32)]))->isConfigured());
    }

    #[DataProvider('invalidKeys')]
    public function test_missing_short_and_header_unsafe_keys_disable_the_adapter(string $key): void
    {
        self::assertFalse((new Configuration(['base_url' => 'https://disk.example.com', 'chat_id' => '-100123456', 'api_key' => $key]))->isConfigured());
    }

    public static function invalidKeys(): array
    {
        return [[''], ['short'], [str_repeat('k', 31)], [str_repeat('k', 32)."\r\nInjected: header"]];
    }

    public function test_explicit_endpoint_takes_precedence_and_derives_download_base(): void
    {
        $config = new Configuration(['base_url' => 'https://old.example.com', 'upload_url' => 'https://disk.example.com/prefix/api/telegram/send']);
        self::assertSame('https://disk.example.com/prefix', $config->baseUrl());
        self::assertSame('https://disk.example.com/prefix/api/telegram/send', $config->uploadUrl());
    }

    #[DataProvider('invalidEndpoints')]
    public function test_invalid_or_cleared_endpoint_does_not_fall_back(string $url): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new Configuration(['base_url' => 'https://old.example.com', 'upload_url' => $url]))->uploadUrl();
    }

    public static function invalidEndpoints(): array
    {
        return [[''], ['https://disk.example.com'], ['https://disk.example.com/api/telegram/send/'], ['https://disk.example.com/api/telegram/send?key=secret']];
    }

    public static function invalidUrls(): array
    {
        return [['http://disk.example.com'], ['https://user:secret@disk.example.com'], ['https://disk.example.com?token=x'], ['https://disk.example.com/#key']];
    }
}
