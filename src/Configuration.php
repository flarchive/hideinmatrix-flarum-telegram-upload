<?php

namespace HideInMatrix\TelegramUpload;

use InvalidArgumentException;

final class Configuration
{
    public const MAX_BYTES = 10 * 1024 * 1024;

    public function __construct(private readonly array $values = []) {}

    public function baseUrl(): string
    {
        $url = array_key_exists('upload_url', $this->values)
            ? (string) $this->values['upload_url']
            : rtrim((string) ($this->values['base_url'] ?? ''), '/').'/api/telegram/send';
        $parts = parse_url($url);

        if (!$parts || ($parts['scheme'] ?? '') !== 'https' || empty($parts['host'])
            || isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment'])) {
            throw new InvalidArgumentException('Configure an HTTPS tg-disk URL without credentials, query or fragment.');
        }

        if (!str_ends_with($parts['path'] ?? '', '/api/telegram/send')) {
            throw new InvalidArgumentException('The upload URL must end in /api/telegram/send.');
        }

        return substr($url, 0, -strlen('/api/telegram/send'));
    }

    public function uploadUrl(): string
    {
        return $this->baseUrl().'/api/telegram/send';
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
            $this->chatId();
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
}
