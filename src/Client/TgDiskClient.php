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
    public function upload(string $uuid, mixed $contents, int $size, ?string $originalName = null, string $mime = 'application/octet-stream'): UploadResult
    {
        if ($size < 0 || $size > Configuration::MAX_BYTES) {
            throw new RuntimeException('Attachment exceeds the 10 MiB limit.');
        }

        // tg-disk selects sendDocument for .bin; image and GIF bytes stay intact.
        $provider = $this->configuration->provider();
        $name = $provider === 'telegram' ? $uuid.'.bin' : ($originalName ?: $uuid.'.bin');
        $multipart = [
            ['name' => 'fileName', 'contents' => $name],
            ['name' => 'file', 'contents' => $contents, 'filename' => $name, 'headers' => ['Content-Type' => $mime]],
        ];
        if ($provider === 'telegram') {
            $multipart[] = ['name' => 'chatId', 'contents' => $this->configuration->chatId()];
        }
        $response = $this->http->request('POST', $this->configuration->uploadUrl(), [
            'headers' => ['X-API-Key' => $this->configuration->apiKey()],
            'multipart' => $multipart,
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
        if (($result['code'] ?? null) !== 200 || !Configuration::validStorageId($id, $provider)) {
            throw new RuntimeException('tg-disk returned no valid file ID.');
        }
        if ((int) ($result['data']['file_size'] ?? -1) !== $size) {
            throw new RuntimeException('tg-disk returned an unexpected file size.');
        }

        return new UploadResult($provider, $id, $this->configuration->fileUrl($id));
    }

    public function download(string $fileId): ResponseInterface
    {
        if (!Configuration::validFileId($fileId)) {
            throw new RuntimeException('Invalid Telegram file ID.');
        }

        return $this->downloadUrl($this->configuration->baseUrl().'/file/'.rawurlencode($fileId));
    }

    public function downloadUrl(string $url): ResponseInterface
    {
        if (!Configuration::validPublicUrl($url)) {
            throw new RuntimeException('Invalid public attachment URL.');
        }
        // Public reads never carry the upload API key.
        // Keep the private API key off public file/cache requests.
        return $this->http->request('GET', $url, [
            'stream' => true,
            'timeout' => 60,
            'connect_timeout' => 10,
            'allow_redirects' => false,
            'http_errors' => false,
        ]);
    }
}
