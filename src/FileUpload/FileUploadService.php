<?php

namespace Doppar\Ripple\FileUpload;

use Doppar\Ripple\Support\Config;

final class FileUploadService
{
    public function storeTemporary(mixed $file, string $componentId): string
    {
        $disk = $this->diskName();
        $base = trim((string) Config::get('ripple.file_upload.temporary_path', 'ripple/temp'), '/');
        $id = bin2hex(random_bytes(16));
        $original = method_exists($file, 'getClientOriginalName')
            ? (string) $file->getClientOriginalName()
            : 'upload.bin';
        $ext = pathinfo($original, PATHINFO_EXTENSION);
        $filename = $id . ($ext !== '' ? '.' . $ext : '');
        $relative = $base . '/' . $componentId . '/' . $filename;

        if (method_exists($file, 'storeAs')) {
            $file->storeAs($base . '/' . $componentId, $filename, $disk);
        } elseif (is_object($file) && method_exists($file, 'getRealPath')) {
            $contents = file_get_contents($file->getRealPath());
            if ($contents !== false) {
                $full = $this->absolutePath($relative, $disk);
                $dir = dirname($full);
                if (! is_dir($dir)) {
                    mkdir($dir, 0775, true);
                }
                file_put_contents($full, $contents);
            }
        }

        $this->writeMeta($id, [
            'path' => $relative,
            'disk' => $disk,
            'original' => $original,
            'component' => $componentId,
            'created' => time(),
        ]);

        return $id;
    }

    public function getTemporaryPath(string $identifier): ?string
    {
        $meta = $this->readMeta($identifier);

        return is_array($meta) ? ($meta['path'] ?? null) : null;
    }

    public function getTemporaryAbsolutePath(string $identifier): ?string
    {
        $meta = $this->readMeta($identifier);
        if (! is_array($meta) || empty($meta['path'])) {
            return null;
        }

        $full = $this->absolutePath((string) $meta['path'], isset($meta['disk']) ? (string) $meta['disk'] : null);

        return is_file($full) ? $full : null;
    }

    public function getTemporaryFileOriginalName(string $identifier): ?string
    {
        $meta = $this->readMeta($identifier);

        return is_array($meta) ? ($meta['original'] ?? null) : null;
    }

    public function moveToPermanent(string $identifier, string $destinationPath, ?string $disk = null, bool $useOriginalName = false): ?string
    {
        $meta = $this->readMeta($identifier);
        if (! is_array($meta) || empty($meta['path'])) {
            return null;
        }

        $sourceDisk = isset($meta['disk']) ? (string) $meta['disk'] : $this->diskName();
        $destDisk = $disk ?? $sourceDisk;
        $source = $this->absolutePath((string) $meta['path'], $sourceDisk);
        if (! is_file($source)) {
            return null;
        }

        if ($useOriginalName && ! empty($meta['original'])) {
            $destinationPath = rtrim(dirname($destinationPath), '/') . '/' . $meta['original'];
        }

        $destinationPath = ltrim(str_replace('\\', '/', $destinationPath), '/');
        $dest = $this->absolutePath($destinationPath, $destDisk);
        $dir = dirname($dest);
        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        rename($source, $dest);
        $this->deleteMeta($identifier);

        return $destinationPath;
    }

    public function deleteTemporary(string $identifier): bool
    {
        $full = $this->getTemporaryAbsolutePath($identifier);
        if ($full !== null) {
            @unlink($full);
        }
        $this->deleteMeta($identifier);

        return true;
    }

    public function cleanupOldFiles(): int
    {
        $hours = (int) Config::get('ripple.file_upload.cleanup_after_hours', 24);
        $cutoff = time() - ($hours * 3600);
        $metaDir = $this->metaDir();
        if (! is_dir($metaDir)) {
            return 0;
        }

        $deleted = 0;
        foreach (glob($metaDir . '/*.json') ?: [] as $file) {
            $meta = json_decode((string) file_get_contents($file), true);
            if (! is_array($meta) || ($meta['created'] ?? 0) > $cutoff) {
                continue;
            }
            $id = basename($file, '.json');
            $this->deleteTemporary($id);
            $deleted++;
        }

        return $deleted;
    }

    private function diskName(): string
    {
        return (string) Config::get('ripple.file_upload.disk', 'local');
    }

    /**
     * Resolve filesystem root for a disk (filesystem.disks.{name}.root).
     */
    private function diskRoot(?string $disk = null): string
    {
        $disk ??= $this->diskName();

        if (function_exists('config')) {
            try {
                $root = config("filesystem.disks.{$disk}.root");
                if (is_string($root) && $root !== '') {
                    return rtrim($root, '/\\');
                }
            } catch (\Throwable) {
                // fall through
            }
        }

        // Match Doppar's default local disk when config isn't booted (package tests).
        return function_exists('storage_path') ? storage_path('app') : sys_get_temp_dir();
    }

    private function absolutePath(string $relative, ?string $disk = null): string
    {
        $relative = ltrim(str_replace('\\', '/', $relative), '/');

        return $this->diskRoot($disk) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
    }

    /**
     * @param array<string, mixed> $meta
     */
    private function writeMeta(string $id, array $meta): void
    {
        $dir = $this->metaDir();
        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        file_put_contents($dir . '/' . $id . '.json', json_encode($meta, JSON_THROW_ON_ERROR));
    }

    /**
     * @return array<string, mixed>|null
     */
    private function readMeta(string $id): ?array
    {
        $file = $this->metaDir() . '/' . $id . '.json';
        if (! is_file($file)) {
            return null;
        }
        $data = json_decode((string) file_get_contents($file), true);

        return is_array($data) ? $data : null;
    }

    private function deleteMeta(string $id): void
    {
        $file = $this->metaDir() . '/' . $id . '.json';
        if (is_file($file)) {
            @unlink($file);
        }
    }

    private function metaDir(): string
    {
        // Meta stays on the configured upload disk under ripple/meta
        return $this->absolutePath('ripple/meta');
    }
}
