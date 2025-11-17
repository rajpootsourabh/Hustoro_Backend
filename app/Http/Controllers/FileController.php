<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;

class FileController extends Controller
{
    public function show(Request $request, $path)
    {
        if (str_contains($path, '..')) {
            return response()->json(['error' => 'Invalid path'], 400);
        }

        try {
            $disk = Storage::disk('private');

            if (!$disk->exists($path)) {
                return response()->json(['error' => 'File not found'], 404);
            }

            $mimeType = $disk->mimeType($path);
            $inlineTypes = [
                'image/png',
                'image/jpeg',
                'image/gif',
                'image/webp',
                'application/pdf',
            ];

            $download = $request->query('download') === 'true';
            $disposition = $download ? 'attachment' : (in_array($mimeType, $inlineTypes) ? 'inline' : 'attachment');

            return response()->stream(function () use ($disk, $path) {
                echo $disk->get($path);
            }, 200, [
                'Content-Type' => $mimeType,
                'Content-Disposition' => $disposition . '; filename="' . basename($path) . '"',
                'Content-Length' => $disk->size($path),
            ]);
        } catch (\Exception $e) {
            Log::error("File serving error: " . $e->getMessage());
            return response()->json(['error' => 'Internal Server Error'], 500);
        }
    }
}
