<?php

namespace HideInMatrix\TelegramUpload\Client;

use GuzzleHttp\ClientInterface;
use HideInMatrix\TelegramUpload\Configuration;
use HideInMatrix\TelegramUpload\Exceptions\ApiKeyRejectedException;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;

final class TgDiskClient
{
    public function __construct(private readonly ClientInterface $http, private readonly Configuration $configuration) {}

    /** @param resource|string $contents */
    public function upload(string $uuid, mixed $contents, int $size): string
    {
        if ($size < 0 || $size > Configuration::MAX_BYTES) {
            throw new RuntimeException('Attachment exceeds the 10 MiB limit.');
        }

        // tg-disk selects sendDocument for .bin; image and GIF bytes stay intact.
        $name = $uuid.'.bin';
        $response = $this->http->request('POST', $this->configuration->uploadUrl(), [
            'headers' => ['X-API-Key' => $this->configuration->apiKey()],
            'multipart' => [
                ['name' => 'chatId', 'contents' => $this->configuration->chatId()],
                ['name' => 'fileName', 'contents' => $name],
                ['name' => 'file', 'contents' => $contents, 'filename' => $name],
            ],
            'timeout' => 50,
            'connect_timeout' => 10,
            'allow_redirects' => false,
            'http_errors' => false,
        ]);

        $status = $response->getStatusCode();
        if ($status === 401 || $status === 403) {
            // Do not propagate upstream response bodies or request headers.
            throw new ApiKeyRejectedException('tg-disk rejected upload authentication.', $status);
        }
        if ($status !== 200) {
            throw new RuntimeException('tg-disk upload returned HTTP '.$status.'.');
        }
        $result = json_decode((string) $response->getBody(), true, 32, JSON_THROW_ON_ERROR);
        $id = $result['data']['file_id'] ?? null;
        if ($response->getStatusCode() !== 200 || ($result['code'] ?? null) !== 200 || !Configuration::validFileId($id)) {
            throw new RuntimeException('tg-disk returned no valid file ID.');
        }
        if ((int) ($result['data']['file_size'] ?? -1) !== $size) {
            throw new RuntimeException('tg-disk returned an unexpected file size.');
        }

        return $id;
    }

    public function download(string $fileId): ResponseInterface
    {
        if (!Configuration::validFileId($fileId)) {
            throw new RuntimeException('Invalid Telegram file ID.');
        }

        // /file is governed by tg-disk's existing host/referer rules, not assertApiKey.
        // Keep the private API key off public file/cache requests.
        return $this->http->request('GET', $this->configuration->baseUrl().'/file/'.rawurlencode($fileId), [
            'stream' => true,
            'timeout' => 60,
            'connect_timeout' => 10,
            'allow_redirects' => false,
            'http_errors' => false,
        ]);
    }
}
