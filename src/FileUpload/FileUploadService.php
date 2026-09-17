<?php

namespace Doppar\Ripple\FileUpload;

use Doppar\Ripple\Support\Config;
use Phaseolies\Support\File;

final class FileUploadService
{
    public function storeTemporary(mixed $file, string $componentId): string
    {
        $disk = (string) Config::get('ripple.file_upload.disk', 'local');
        $base = trim((string) Config::get('ripple.file_upload.temporary_path', 'ripple/temp'), '/');
        $id = bin2hex(random_bytes(16));
        $original = method_exists($file, 'getClientOriginalName')
            ? (string) $file->getClientOriginalName()
            : 'upload.bin';
        $ext = pathinfo($original, PATHINFO_EXTENSION);
        $filename = $id . ($ext !== '' ? '.' . $ext : '');
        $path = $base . '/' . $componentId . '/' . $filename;

        if (method_exists($file, 'storeAs')) {
            $dir = $base . '/' . $componentId;
            $file->storeAs($dir, $filename, $disk);
        } elseif (is_object($file) && method_exists($file, 'getRealPath')) {
            $contents = file_get_contents($file->getRealPath());
            if ($contents !== false && function_exists('storage_path')) {
                $full = storage_path('app/' . $path);
                $dir = dirname($full);
                if (!is_dir($dir)) {
                    mkdir($dir, 0775, true);
                }
                file_put_contents($full, $contents);
            }
        }

        $meta = [
            'path' => $path,
            'original' => $original,
            'component' => $componentId,
            'created' => time(),
        ];
        $this->writeMeta($id, $meta);

        return $id;
    }

    public function getTemporaryPath(string $identifier): ?string
    {
        $meta = $this->readMeta($identifier);

        return is_array($meta) ? ($meta['path'] ?? null) : null;
    }

    public function getTemporaryFileOriginalName(string $identifier): ?string
    {
        $meta = $this->readMeta($identifier);

        return is_array($meta) ? ($meta['original'] ?? null) : null;
    }

    public function moveToPermanent(string $identifier, string $destinationPath, ?string $disk = null, bool $useOriginalName = false): ?string
    {
        $meta = $this->readMeta($identifier);
        if (!is_array($meta) || empty($meta['path'])) {
            return null;
        }

        $disk ??= (string) Config::get('ripple.file_upload.disk', 'local');
        $source = storage_path('app/' . $meta['path']);
        if (!is_file($source)) {
            return null;
        }

        if ($useOriginalName && !empty($meta['original'])) {
            $destinationPath = rtrim(dirname($destinationPath), '/') . '/' . $meta['original'];
        }

        $dest = storage_path('app/' . ltrim($destinationPath, '/'));
        $dir = dirname($dest);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        rename($source, $dest);
        $this->deleteMeta($identifier);

        return ltrim($destinationPath, '/');
    }

    public function deleteTemporary(string $identifier): bool
    {
        $path = $this->getTemporaryPath($identifier);
        if ($path) {
            $full = storage_path('app/' . $path);
            if (is_file($full)) {
                @unlink($full);
            }
        }
        $this->deleteMeta($identifier);

        return true;
    }

    public function cleanupOldFiles(): int
    {
        $hours = (int) Config::get('ripple.file_upload.cleanup_after_hours', 24);
        $cutoff = time() - ($hours * 3600);
        $metaDir = $this->metaDir();
        if (!is_dir($metaDir)) {
            return 0;
        }

        $deleted = 0;
        foreach (glob($metaDir . '/*.json') ?: [] as $file) {
            $meta = json_decode((string) file_get_contents($file), true);
            if (!is_array($meta) || ($meta['created'] ?? 0) > $cutoff) {
                continue;
            }
            $id = basename($file, '.json');
            $this->deleteTemporary($id);
            $deleted++;
        }

        return $deleted;
    }

    /**
     * @param array<string, mixed> $meta
     */
    private function writeMeta(string $id, array $meta): void
    {
        $dir = $this->metaDir();
        if (!is_dir($dir)) {
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
        if (!is_file($file)) {
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
        return storage_path('app/ripple/meta');
    }
}
