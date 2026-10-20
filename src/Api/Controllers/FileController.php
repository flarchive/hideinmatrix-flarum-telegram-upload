<?php

namespace HideInMatrix\TelegramUpload\Api\Controllers;

use FoF\Upload\Api\Controllers\DownloadController;
use FoF\Upload\File;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class FileController implements RequestHandlerInterface
{
    public function __construct(private readonly DownloadController $downloads) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        // FoF retains download permissions, tag restrictions and download logging.
        $response = $this->downloads->handle($request);
        if ($response->getStatusCode() !== 200) {
            return $response;
        }
        $file = File::query()->where('uuid', $request->getQueryParams()['uuid'] ?? '')->first();
        if ($file && $file->upload_method === 'telegram' && preg_match('~^image/(jpeg|png|apng|gif|webp|avif|bmp|tiff)$~D', $file->type)) {
            return $response->withHeader('Content-Type', $file->type)->withHeader('Content-Disposition', 'inline');
        }

        return $response;
    }
}
