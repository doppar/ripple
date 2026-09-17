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
    public function __construct(private readonly FileUploadService $uploads) {}

    public function upload(Request $request): JsonResponse
    {
        try {
            $componentId = (string) $request->input('i', $request->input('componentId', ''));
            $property = (string) $request->input('p', $request->input('property', ''));
            $file = $request->file('file') ?? null;

            if ($componentId === '' || $property === '' || $file === null) {
                return new JsonResponse([
                    'message' => 'Missing required upload parameters.',
                    'errors' => new \stdClass(),
                ], 400);
            }

            if (is_array($file)) {
                $file = $file[0] ?? null;
            }

            $maxKb = (int) Config::get('ripple.file_upload.max_size', 12288);
            $size = method_exists($file, 'getSize') ? (int) $file->getSize() : 0;
            if ($size > $maxKb * 1024) {
                return new JsonResponse([
                    'message' => 'File too large.',
                    'errors' => new \stdClass(),
                ], 422);
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
        $path = $this->uploads->getTemporaryPath($id);
        if ($path === null) {
            return new JsonResponse(['message' => 'Not found', 'errors' => new \stdClass()], 404);
        }

        $full = storage_path('app/' . $path);
        if (!is_file($full)) {
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
}
