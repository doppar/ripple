<?php

namespace Doppar\Ripple\Http;

use Doppar\Ripple\FileUpload\FileUploadService;
use Doppar\Ripple\Support\Config;
use Phaseolies\Error\JsonErrorRenderer;
use Phaseolies\Http\Request;
use Phaseolies\Http\Response;
use Phaseolies\Http\Response\JsonResponse;

final class UploadController
{
    public function __construct(private readonly FileUploadService $uploads)
    {
    }

    public function upload(Request $request): JsonResponse
    {
        try {
            $componentId = (string) $request->input('i', $request->input('componentId', ''));
            $property = (string) $request->input('p', $request->input('property', ''));
            $file = $request->file('file') ?? null;

            if ($componentId === '' || $property === '' || $file === null) {
                return $this->fail('Missing required upload parameters.', 400);
            }

            if (is_array($file)) {
                $file = $file[0] ?? null;
            }

            if ($file === null) {
                return $this->fail('Missing required upload parameters.', 400);
            }

            // PHP rejected the upload (often upload_max_filesize=2M) before we can read size.
            if (method_exists($file, 'isValid') && ! $file->isValid()) {
                $code = method_exists($file, 'getError') ? (int) $file->getError() : UPLOAD_ERR_NO_FILE;

                return $this->fail($this->phpUploadErrorMessage($code), 422);
            }

            $maxKb = (int) Config::get('ripple.file_upload.max_size', 12288);
            $size = $this->safeSize($file);
            if ($size > $maxKb * 1024) {
                return $this->fail(
                    sprintf('File too large. Max allowed is %d KB.', $maxKb),
                    422,
                );
            }

            $id = $this->uploads->storeTemporary($file, $componentId);

            return new JsonResponse([
                'ok' => true,
                'id' => $id,
                'filename' => method_exists($file, 'getClientOriginalName') ? $file->getClientOriginalName() : null,
                'size' => $size,
            ]);
        } catch (\Throwable $e) {
            return (new JsonErrorRenderer())->render($e, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function preview(Request $request): Response
    {
        $id = (string) $request->input('id', '');
        $full = $this->uploads->getTemporaryAbsolutePath($id);
        if ($full === null) {
            return new JsonResponse(['message' => 'Not found', 'errors' => new \stdClass()], 404);
        }

        $contents = file_get_contents($full);
        $ext = strtolower(pathinfo($full, PATHINFO_EXTENSION));
        $mime = match ($ext) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            default => 'application/octet-stream',
        };

        return new Response($contents === false ? '' : $contents, 200, [
            'Content-Type' => $mime,
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }

    private function fail(string $message, int $status): JsonResponse
    {
        return new JsonResponse([
            'ok' => false,
            'message' => $message,
            'errors' => new \stdClass(),
        ], $status);
    }

    private function safeSize(mixed $file): int
    {
        if (! method_exists($file, 'getSize')) {
            return 0;
        }

        try {
            return (int) $file->getSize();
        } catch (\Throwable) {
            return 0;
        }
    }

    private function phpUploadErrorMessage(int $code): string
    {
        $ini = (string) (ini_get('upload_max_filesize') ?: 'unknown');

        return match ($code) {
            UPLOAD_ERR_INI_SIZE,
            UPLOAD_ERR_FORM_SIZE => "File exceeds PHP upload_max_filesize ({$ini}). Raise that (and post_max_size) above ripple.file_upload.max_size.",
            UPLOAD_ERR_PARTIAL => 'File was only partially uploaded.',
            UPLOAD_ERR_NO_FILE => 'No file was uploaded.',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary upload folder.',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write uploaded file to disk.',
            UPLOAD_ERR_EXTENSION => 'A PHP extension blocked the upload.',
            default => 'Upload failed (error ' . $code . ').',
        };
    }
}
