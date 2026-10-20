<?php

namespace HideInMatrix\TelegramUpload\Downloaders;

use FoF\Upload\Commands\Download;
use FoF\Upload\Contracts\Downloader;
use FoF\Upload\File;
use HideInMatrix\TelegramUpload\Client\TgDiskClient;
use HideInMatrix\TelegramUpload\Configuration;
use Laminas\Diactoros\Response\EmptyResponse;
use Psr\Http\Message\ResponseInterface;

final class TelegramDownloader implements Downloader
{
    public function __construct(private readonly TgDiskClient $client) {}

    public function forFile(File $file): bool
    {
        return $file->upload_method === 'telegram';
    }

    public function download(File $file, ?Download $command = null): ResponseInterface
    {
        $provider = str_starts_with((string) $file->path, 'r2/') ? 'r2' : 'telegram';
        if (!Configuration::validStorageId($file->remote_id, $provider)) {
            return new EmptyResponse(404);
        }

        try {
            $suffix = ($provider === 'r2' ? '/r2/' : '/file/').rawurlencode($file->remote_id);
            if (Configuration::validPublicUrl((string) $file->url) && str_ends_with($file->url, $suffix)) {
                $response = $this->client->downloadUrl($file->url);
            } elseif ($provider === 'telegram') {
                // Older records contain a forum proxy URL; keep resolving their Telegram ID.
                $response = $this->client->download($file->remote_id);
            } else {
                return new EmptyResponse(404);
            }
        } catch (\Throwable) {
            return new EmptyResponse(502);
        }
        if ($response->getStatusCode() !== 200) {
            return new EmptyResponse($response->getStatusCode() === 404 ? 404 : 502);
        }
        $length = $response->getHeaderLine('Content-Length');
        if ($length !== '' && (int) $length !== (int) $file->size) {
            return new EmptyResponse(502);
        }

        $name = str_replace(["\r", "\n", '"', '\\'], '_', $file->base_name);

        return $response
            ->withHeader('Content-Type', 'application/octet-stream')
            ->withHeader('Content-Disposition', 'attachment; filename="'.preg_replace('/[^\x20-\x7E]/', '_', $name).'"; filename*=UTF-8\'\''.rawurlencode($name))
            ->withHeader('X-Content-Type-Options', 'nosniff')
            ->withHeader('Cache-Control', 'private, no-store');
    }
}
