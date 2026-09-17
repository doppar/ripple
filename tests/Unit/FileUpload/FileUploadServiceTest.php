<?php

namespace Doppar\Ripple\Tests\Unit\FileUpload;

use Doppar\Ripple\FileUpload\FileUploadService;
use Doppar\Ripple\Tests\Support\AppHarness;
use Doppar\Ripple\Tests\TestCase;

final class FileUploadServiceTest extends TestCase
{
    private AppHarness $app;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app = new AppHarness(sys_get_temp_dir() . '/ripple-fu-' . bin2hex(random_bytes(4)));
    }

    protected function tearDown(): void
    {
        $this->app->destroy();
        parent::tearDown();
    }

    public function test_store_move_and_delete_temporary(): void
    {
        $service = new FileUploadService();
        $tmp = tempnam(sys_get_temp_dir(), 'up');
        self::assertNotFalse($tmp);
        file_put_contents($tmp, 'hello-ripple');

        $file = new class($tmp) {
            public function __construct(private string $path) {}

            public function getClientOriginalName(): string
            {
                return 'note.txt';
            }

            public function getRealPath(): string
            {
                return $this->path;
            }
        };

        $id = $service->storeTemporary($file, 'comp-1');
        $this->assertNotSame('', $id);
        $this->assertSame('note.txt', $service->getTemporaryFileOriginalName($id));
        $rel = $service->getTemporaryPath($id);
        $this->assertNotNull($rel);
        $this->assertFileExists(storage_path('app/' . $rel));

        $moved = $service->moveToPermanent($id, 'ripple/permanent/out.txt');
        $this->assertSame('ripple/permanent/out.txt', $moved);
        $this->assertFileExists(storage_path('app/ripple/permanent/out.txt'));
        $this->assertNull($service->getTemporaryPath($id));

        @unlink($tmp);
    }

    public function test_delete_temporary_and_cleanup(): void
    {
        $service = new FileUploadService();
        $tmp = tempnam(sys_get_temp_dir(), 'up');
        self::assertNotFalse($tmp);
        file_put_contents($tmp, 'x');

        $file = new class($tmp) {
            public function __construct(private string $path) {}

            public function getClientOriginalName(): string
            {
                return 'a.bin';
            }

            public function getRealPath(): string
            {
                return $this->path;
            }
        };

        $id = $service->storeTemporary($file, 'c2');
        $path = $service->getTemporaryPath($id);
        $this->assertNotNull($path);

        $meta = storage_path('app/ripple/meta/' . $id . '.json');
        $data = json_decode((string) file_get_contents($meta), true);
        $data['created'] = time() - 86400 * 48;
        file_put_contents($meta, json_encode($data));

        $deleted = $service->cleanupOldFiles();
        $this->assertGreaterThanOrEqual(1, $deleted);
        $this->assertNull($service->getTemporaryPath($id));

        @unlink($tmp);
    }

    public function test_unknown_identifier_returns_null(): void
    {
        $service = new FileUploadService();
        $this->assertNull($service->getTemporaryPath('missing'));
        $this->assertNull($service->moveToPermanent('missing', 'x'));
        $this->assertTrue($service->deleteTemporary('missing'));
    }
}
