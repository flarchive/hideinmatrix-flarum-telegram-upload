<?php

namespace HideInMatrix\TelegramUpload;

use InvalidArgumentException;

final class Configuration
{
    public const MAX_BYTES = 10 * 1024 * 1024;

    public function __construct(private readonly array $values = []) {}

    public function baseUrl(): string
    {
        // Read old endpoint settings until the administrator saves the new base URL.
        $legacy = array_key_exists('upload_url', $this->values);
        $url = $legacy ? (string) $this->values['upload_url'] : (string) ($this->values['base_url'] ?? '');
        if (!self::validPublicUrl($url)) {
            throw new InvalidArgumentException('Configure an HTTPS tg-disk URL without credentials, query or fragment.');
        }
        if ($legacy && !str_ends_with($url, '/api/telegram/send')) {
            throw new InvalidArgumentException('The upload URL must end in /api/telegram/send.');
        }
        if ($legacy) {
            $url = substr($url, 0, -strlen('/api/telegram/send'));
        } elseif (preg_match('~/api/(telegram|r2)/send/?$~', $url)) {
            throw new InvalidArgumentException('Configure the tg-disk base URL, not an upload endpoint.');
        }

        return rtrim($url, '/');
    }

    public function provider(): string
    {
        $provider = (string) ($this->values['provider'] ?? 'telegram');
        if (!in_array($provider, ['telegram', 'r2'], true)) {
            throw new InvalidArgumentException('Select Telegram or R2 storage.');
        }

        return $provider;
    }

    public function uploadUrl(): string
    {
        return $this->baseUrl().'/api/'.$this->provider().'/send';
    }

    public function fileUrl(string $id): string
    {
        if (!self::validStorageId($id, $this->provider())) {
            throw new InvalidArgumentException('Invalid storage file ID.');
        }

        return $this->baseUrl().($this->provider() === 'r2' ? '/r2/' : '/file/').rawurlencode($id);
    }

    public function chatId(): string
    {
        $id = (string) ($this->values['chat_id'] ?? '');
        if (!preg_match('/^-?[0-9]+$/D', $id)) {
            throw new InvalidArgumentException('Configure the Telegram numeric chat_id.');
        }

        return $id;
    }

    public function apiKey(): string
    {
        $key = (string) ($this->values['api_key'] ?? '');
        if (strlen($key) < 32 || preg_match('/[\x00-\x1F\x7F]/', $key)) {
            throw new InvalidArgumentException('Configure a private tg-disk api_key of at least 32 characters without control characters.');
        }

        return $key;
    }

    public function isConfigured(): bool
    {
        try {
            $this->baseUrl();
            if ($this->provider() === 'telegram') {
                $this->chatId();
            }
            $this->apiKey();

            return true;
        } catch (InvalidArgumentException) {
            return false;
        }
    }

    public static function validFileId(mixed $id): bool
    {
        return is_string($id) && preg_match('/^[A-Za-z0-9_-]{6,255}$/D', $id) === 1;
    }

    public static function validStorageId(mixed $id, string $provider): bool
    {
        if ($provider === 'telegram') {
            return self::validFileId($id);
        }

        // tg-disk generates a flat UUID key with an optional filename extension.
        return $provider === 'r2' && is_string($id)
            && preg_match('/^[A-Za-z0-9_-][A-Za-z0-9_.-]{5,254}$/D', $id) === 1
            && !str_contains($id, '..');
    }

    public static function validPublicUrl(string $url): bool
    {
        $parts = parse_url($url);

        return !preg_match('/[\x00-\x20\x7F\\\\]/', $url) && filter_var($url, FILTER_VALIDATE_URL)
            && $parts && ($parts['scheme'] ?? '') === 'https' && !empty($parts['host'])
            && !isset($parts['user']) && !isset($parts['pass'])
            && !isset($parts['query']) && !isset($parts['fragment']);
    }
}
