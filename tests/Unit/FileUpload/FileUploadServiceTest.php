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

        $file = new class ($tmp) {
            public function __construct(private string $path)
            {
            }

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

        $file = new class ($tmp) {
            public function __construct(private string $path)
            {
            }

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

    public function test_component_id_with_path_traversal_is_rejected(): void
    {
        $service = new FileUploadService();
        $tmp = tempnam(sys_get_temp_dir(), 'up');
        self::assertNotFalse($tmp);
        file_put_contents($tmp, 'evil');

        $file = new class ($tmp) {
            public function __construct(private string $path)
            {
            }

            public function getClientOriginalName(): string
            {
                return 'note.txt';
            }

            public function getRealPath(): string
            {
                return $this->path;
            }
        };

        $this->assertFalse($service->isSafeComponentId('../../../public/x'));
        $this->assertFalse($service->isSafeComponentId('a/b'));
        $this->assertFalse($service->isSafeComponentId(''));
        $this->assertTrue($service->isSafeComponentId('3f9a1c0b7d2e4a68'));

        try {
            $this->expectException(\InvalidArgumentException::class);
            $this->expectExceptionMessage('Invalid upload component id.');
            $service->storeTemporary($file, '../../../public/x');
        } finally {
            $this->assertDirectoryDoesNotExist(storage_path('public/x'));
            @unlink($tmp);
        }
    }

    public function test_executable_extensions_are_rejected(): void
    {
        $service = new FileUploadService();

        foreach (['shell.php', 'SHELL.PHP', 'x.phtml', 'x.phar', '.htaccess', '.user.ini', 'shell.php.jpg'] as $name) {
            $this->assertFalse($service->isAllowedFilename($name), $name);
        }
        foreach (['avatar.jpg', 'report.pdf', 'data.csv', 'README'] as $name) {
            $this->assertTrue($service->isAllowedFilename($name), $name);
        }

        $tmp = tempnam(sys_get_temp_dir(), 'up');
        self::assertNotFalse($tmp);
        file_put_contents($tmp, '<?php echo 1;');

        $file = new class ($tmp) {
            public function __construct(private string $path)
            {
            }

            public function getClientOriginalName(): string
            {
                return 'shell.php';
            }

            public function getRealPath(): string
            {
                return $this->path;
            }
        };

        try {
            $this->expectException(\InvalidArgumentException::class);
            $this->expectExceptionMessage('Upload file type is not allowed.');
            $service->storeTemporary($file, 'comp-1');
        } finally {
            @unlink($tmp);
        }
    }

    public function test_meta_id_with_path_traversal_is_rejected(): void
    {
        $service = new FileUploadService();
        $this->assertNull($service->getTemporaryPath('../etc/passwd'));
        $this->assertNull($service->getTemporaryAbsolutePath('abcd/../efgh'));
        $this->assertNull($service->getTemporaryFileOriginalName('..'));
    }

    public function test_use_original_name_rejects_path_traversal(): void
    {
        $service = new FileUploadService();
        $tmp = tempnam(sys_get_temp_dir(), 'up');
        self::assertNotFalse($tmp);
        file_put_contents($tmp, 'evil');

        $file = new class ($tmp) {
            public function __construct(private string $path)
            {
            }

            public function getClientOriginalName(): string
            {
                return 'safe.txt';
            }

            public function getRealPath(): string
            {
                return $this->path;
            }
        };

        $id = $service->storeTemporary($file, 'comp-evil');
        $metaPath = storage_path('app/ripple/meta/' . $id . '.json');
        $meta = json_decode((string) file_get_contents($metaPath), true);
        $meta['original'] = '../../../public/shell.php';
        file_put_contents($metaPath, json_encode($meta));

        $outside = storage_path('app/public/shell.php');
        @unlink($outside);

        try {
            $this->expectException(\InvalidArgumentException::class);
            $this->expectExceptionMessageIsOrContains('Invalid upload filename');
            $service->moveToPermanent($id, 'ripple/permanent/out.txt', useOriginalName: true);
        } finally {
            $this->assertFileDoesNotExist($outside);
            @unlink($tmp);
        }
    }

    public function test_use_original_name_with_safe_basename(): void
    {
        $service = new FileUploadService();
        $tmp = tempnam(sys_get_temp_dir(), 'up');
        self::assertNotFalse($tmp);
        file_put_contents($tmp, 'ok');

        $file = new class ($tmp) {
            public function __construct(private string $path)
            {
            }

            public function getClientOriginalName(): string
            {
                return 'report.pdf';
            }

            public function getRealPath(): string
            {
                return $this->path;
            }
        };

        $id = $service->storeTemporary($file, 'comp-ok');
        $moved = $service->moveToPermanent($id, 'ripple/permanent/placeholder.bin', useOriginalName: true);

        $this->assertSame('ripple/permanent/report.pdf', $moved);
        $this->assertFileExists(storage_path('app/ripple/permanent/report.pdf'));

        @unlink($tmp);
    }
}
