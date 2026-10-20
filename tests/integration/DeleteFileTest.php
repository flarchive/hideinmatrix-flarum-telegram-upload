<?php

namespace HideInMatrix\TelegramUpload\Tests\integration;

use Flarum\Testing\integration\TestCase;
use FoF\Upload\Contracts\UploadAdapter;
use FoF\Upload\File;
use FoF\Upload\Helpers\Util;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use HideInMatrix\TelegramUpload\Client\TgDiskClient;
use HideInMatrix\TelegramUpload\Configuration;

final class DeleteFileTest extends TestCase
{
    use \Flarum\Testing\integration\RetrievesAuthorizedUsers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->extension('fof-upload', 'hideinmatrix-telegram-upload');
        $this->prepareDatabase(['users' => [$this->normalUser()]]);
    }

    private function file(string $uuid, string $method = 'telegram'): File
    {
        $file = (new File())->forceFill([
            'uuid' => $uuid, 'base_name' => 'deletion-fixture.txt',
            'size' => 7, 'type' => 'text/plain', 'actor_id' => 2,
            'upload_method' => $method, 'remote_id' => 'test_file_id',
            'path' => 'telegram/test_file_id', 'url' => 'https://disk.example.com/file/test_file_id',
            'tag' => $this->app()->getContainer()->make(Util::class)->getTemplate('file'), 'hidden' => false, 'shared' => false,
        ]);
        $file->save();

        return $file;
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('storageProviders')]
    public function test_admin_removes_only_the_media_record_without_configuration_or_remote_calls(string $provider, string $id): void
    {
        $this->config('telegram-upload', []);
        // Even a changed MIME mapping must not send a historical Telegram file to local storage.
        $this->setting('fof-upload.mimeTypes', json_encode(['^text/plain$' => ['adapter' => 'local', 'template' => 'file']]));
        $container = $this->app()->getContainer();
        $history = [];
        $stack = HandlerStack::create(new MockHandler());
        $stack->push(Middleware::history($history));
        $container->instance(TgDiskClient::class, new TgDiskClient(new Client(['handler' => $stack]), new Configuration()));
        $target = $this->file('00000000-0000-4000-8000-000000000002');
        $other = $this->file('00000000-0000-4000-8000-000000000003');
        $target->forceFill(['path' => $provider.'/'.$id, 'remote_id' => $id, 'url' => 'https://disk.example.com/'.($provider === 'r2' ? 'r2/' : 'file/').$id])->save();

        $response = $this->send($this->requestAsUser($this->request('DELETE', '/api/fof/upload/delete/'.$target->uuid), 1));

        self::assertSame(204, $response->getStatusCode(), (string) $response->getBody());
        self::assertNull(File::find($target->id));
        self::assertNotNull(File::find($other->id));
        self::assertSame([], $history);
    }

    public static function storageProviders(): array
    {
        return [['telegram', 'test_file_id'], ['r2', 'fixture-key.avif']];
    }

    public function test_guest_and_owner_cannot_bypass_fof_delete_permissions(): void
    {
        $file = $this->file('00000000-0000-4000-8000-000000000002');
        $request = $this->request('DELETE', '/api/fof/upload/delete/'.$file->uuid)->withAttribute('bypassCsrfToken', true);
        self::assertSame(403, $this->send($request)->getStatusCode());
        self::assertSame(403, $this->send($this->requestAsUser($request, 2))->getStatusCode());
        self::assertNotNull(File::find($file->id));
    }

    public function test_successful_other_storage_deletion_removes_the_record(): void
    {
        $file = $this->file('00000000-0000-4000-8000-000000000002', 'local');
        $adapter = $this->createMock(UploadAdapter::class);
        $adapter->expects(self::once())->method('delete')->willReturn(true);
        $util = $this->getMockBuilder(Util::class)->disableOriginalConstructor()->onlyMethods(['isPrivateShared', 'getStorageAdapterForFile'])->getMock();
        $util->method('isPrivateShared')->willReturn(false);
        $util->expects(self::once())->method('getStorageAdapterForFile')->willReturn($adapter);
        $this->app()->getContainer()->instance(Util::class, $util);

        $response = $this->send($this->requestAsUser($this->request('DELETE', '/api/fof/upload/delete/'.$file->uuid), 1));

        self::assertSame(204, $response->getStatusCode(), (string) $response->getBody());
        self::assertNull(File::find($file->id));
    }

    public function test_other_storage_adapters_keep_their_original_delete_behavior(): void
    {
        $file = $this->file('00000000-0000-4000-8000-000000000002', 'local');
        $adapter = $this->createMock(UploadAdapter::class);
        $adapter->expects(self::once())->method('delete')->with(self::callback(fn ($value) => $value->id === $file->id))->willReturn(false);
        $util = $this->getMockBuilder(Util::class)->disableOriginalConstructor()->onlyMethods(['isPrivateShared', 'getStorageAdapterForFile'])->getMock();
        $util->method('isPrivateShared')->willReturn(false);
        $util->expects(self::once())->method('getStorageAdapterForFile')->willReturn($adapter);
        $this->app()->getContainer()->instance(Util::class, $util);

        $response = $this->send($this->requestAsUser($this->request('DELETE', '/api/fof/upload/delete/'.$file->uuid), 1));

        self::assertSame(422, $response->getStatusCode());
        self::assertNotNull(File::find($file->id));
    }
}
