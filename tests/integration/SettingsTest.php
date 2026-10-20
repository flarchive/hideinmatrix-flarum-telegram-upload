<?php

namespace HideInMatrix\TelegramUpload\Tests\integration;

use Flarum\Settings\SettingsRepositoryInterface;
use Flarum\Testing\integration\TestCase;
use HideInMatrix\TelegramUpload\Configuration;

final class SettingsTest extends TestCase
{
    use \Flarum\Testing\integration\RetrievesAuthorizedUsers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->extension('fof-upload', 'hideinmatrix-telegram-upload');
        $this->config('telegram-upload', ['base_url' => 'https://old.example.com', 'chat_id' => '-1001', 'api_key' => str_repeat('o', 32)]);
        $this->prepareDatabase(['users' => [$this->normalUser()]]);
    }

    private function values(): array
    {
        return [
            'hideinmatrix-telegram-upload.base_url' => 'https://disk.example.com',
            'hideinmatrix-telegram-upload.provider' => 'telegram',
            'hideinmatrix-telegram-upload.chat_id' => '-100123456',
            'hideinmatrix-telegram-upload.api_key' => str_repeat('s', 32),
        ];
    }

    public function test_admin_can_save_site_provider_and_credentials(): void
    {
        $response = $this->send($this->requestAsUser($this->request('POST', '/api/settings')->withParsedBody($this->values()), 1));
        self::assertSame(204, $response->getStatusCode(), (string) $response->getBody());
        $container = $this->app()->getContainer();
        foreach ($this->values() as $key => $value) {
            self::assertSame($value, $container->make(SettingsRepositoryInterface::class)->get($key));
        }
        $container->forgetInstance(Configuration::class);
        $configuration = $container->make(Configuration::class);
        self::assertSame('https://disk.example.com', $configuration->baseUrl());
        self::assertSame('-100123456', $configuration->chatId());
        self::assertSame(str_repeat('s', 32), $configuration->apiKey());
    }

    public function test_guest_and_member_cannot_set_credentials(): void
    {
        $request = $this->request('POST', '/api/settings')->withParsedBody($this->values())->withAttribute('bypassCsrfToken', true);
        self::assertSame(403, $this->send($request)->getStatusCode());
        self::assertSame(403, $this->send($this->requestAsUser($request, 2))->getStatusCode());
        self::assertNull($this->app()->getContainer()->make(SettingsRepositoryInterface::class)->get('hideinmatrix-telegram-upload.api_key'));
    }

    public function test_new_site_url_overrides_legacy_endpoint_and_r2_needs_no_channel(): void
    {
        $this->setting('hideinmatrix-telegram-upload.upload_url', 'https://legacy.example.com/api/telegram/send');
        $values = $this->values();
        $values['hideinmatrix-telegram-upload.provider'] = 'r2';
        $values['hideinmatrix-telegram-upload.chat_id'] = '';
        $response = $this->send($this->requestAsUser($this->request('POST', '/api/settings')->withParsedBody($values), 1));
        self::assertSame(204, $response->getStatusCode(), (string) $response->getBody());
        $container = $this->app()->getContainer();
        $container->forgetInstance(Configuration::class);
        $config = $container->make(Configuration::class);
        self::assertTrue($config->isConfigured());
        self::assertSame('https://disk.example.com/api/r2/send', $config->uploadUrl());

        $container->make(SettingsRepositoryInterface::class)->set('hideinmatrix-telegram-upload.base_url', '');
        $container->forgetInstance(Configuration::class);
        self::assertFalse($container->make(Configuration::class)->isConfigured());
    }

    public function test_bad_provider_and_endpoint_in_site_url_are_rejected(): void
    {
        $response = $this->send($this->requestAsUser($this->request('POST', '/api/settings')->withParsedBody([
            'hideinmatrix-telegram-upload.base_url' => 'https://disk.example.com/api/r2/send',
            'hideinmatrix-telegram-upload.provider' => 'unknown',
        ]), 1));
        self::assertSame(422, $response->getStatusCode());
        self::assertCount(2, json_decode((string) $response->getBody(), true)['errors']);
    }

    public function test_bad_values_are_rejected_without_echoing_credentials(): void
    {
        $values = [
            'hideinmatrix-telegram-upload.upload_url' => 'https://disk.example.com/api/telegram/send?key=private-value',
            'hideinmatrix-telegram-upload.chat_id' => 'not-a-channel',
            'hideinmatrix-telegram-upload.api_key' => 'short-private-key',
        ];
        $response = $this->send($this->requestAsUser($this->request('POST', '/api/settings')->withParsedBody($values), 1));
        self::assertSame(422, $response->getStatusCode());
        $body = (string) $response->getBody();
        foreach ($values as $value) {
            self::assertStringNotContainsString($value, $body);
        }
        self::assertCount(3, json_decode($body, true)['errors']);
    }

    public function test_saved_secret_is_absent_from_public_api_and_clearing_disables_upload(): void
    {
        foreach ($this->values() as $key => $value) {
            $this->setting($key, $value);
        }
        $response = $this->send($this->request('GET', '/api'));
        self::assertSame(200, $response->getStatusCode());
        self::assertStringNotContainsString(str_repeat('s', 32), (string) $response->getBody());
        $container = $this->app()->getContainer();
        $response = $this->send($this->requestAsUser($this->request('POST', '/api/settings')->withParsedBody(['hideinmatrix-telegram-upload.api_key' => '']), 1));
        self::assertSame(204, $response->getStatusCode());
        $container->forgetInstance(Configuration::class);
        self::assertFalse($container->make(Configuration::class)->isConfigured());
    }
}
