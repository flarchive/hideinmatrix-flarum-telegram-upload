<?php

namespace HideInMatrix\TelegramUpload\Tests\unit;

use HideInMatrix\TelegramUpload\Diagnostics\StorageCheck;
use PHPUnit\Framework\TestCase;

final class StorageCheckTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir().'/telegram-storage-check-'.bin2hex(random_bytes(8));
        mkdir($this->root);
    }

    protected function tearDown(): void
    {
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->root, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($iterator as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        rmdir($this->root);
    }

    public function test_missing_directories_are_checked_without_creating_them(): void
    {
        foreach ((new StorageCheck())->inspect($this->root) as $result) {
            self::assertTrue($result['ok']);
            self::assertFalse($result['exists']);
        }
        self::assertSame(['.', '..'], scandir($this->root));
    }

    public function test_a_file_blocking_the_storage_path_is_reported(): void
    {
        mkdir($this->root.'/storage');
        file_put_contents($this->root.'/storage/private-shared', 'fixture');
        $checks = (new StorageCheck())->inspect($this->root);
        self::assertFalse($checks['storage/private-shared/files']['ok']);
        self::assertFalse($checks['storage/private-shared/files']['exists']);
        self::assertTrue($checks['storage/tmp']['ok']);
    }
}
