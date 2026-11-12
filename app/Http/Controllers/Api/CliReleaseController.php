<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\CliReleaseManifest;
use Illuminate\Http\JsonResponse;

class CliReleaseController extends Controller
{
    /** Newest CLI release files (public, cached 5 minutes). */
    public function latest(): JsonResponse
    {
        $manifest = CliReleaseManifest::latest();

        if ($manifest === null) {
            return response()->json(['message' => 'No CLI release yet'], 404);
        }

        return response()->json($manifest)->header('Cache-Control', 'public, max-age=300');
    }
}
