<?php

namespace App\Http\Controllers;

use App\Models\DownloadFile;
use App\Support\Downloads;
use App\Support\ReleaseFiles;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * The public /downloads page and the release files, on atglance.live and on the old
 * app.atglance.live host (same files, old paths).
 */
class DownloadController extends Controller
{
    public function index(): View
    {
        $all = Downloads::resolved();
        $products = [];
        foreach (Downloads::PRODUCTS as $p => $name) {
            $latest = $all[$p]['releases'][0] ?? null;
            $products[$p] = [
                'name' => $name,
                'data' => $all[$p],
                'latest' => $latest,
                'files' => ReleaseFiles::forRelease($p, $latest['version'] ?? null),
                'older' => array_slice($all[$p]['releases'], 1, 5),
            ];
        }

        return view('site.downloads', ['products' => $products]);
    }

    /** /downloads/files/{product}/{folder}/{file} */
    public function file(string $product, string $folder, string $file): BinaryFileResponse|Response
    {
        if ($file === 'SHA256SUMS') {
            $files = DownloadFile::where('product', $product)->where('folder', $folder)->get();
            abort_if($files->isEmpty(), 404);

            return $this->sums($files);
        }

        $found = DownloadFile::where(['product' => $product, 'folder' => $folder, 'file_name' => $file])->first();
        abort_unless($found, 404);

        return ReleaseFiles::respond($found);
    }

    /** /downloads/latest/{product}/{file}: the newest upload with this name (stable installer links). */
    public function latest(string $product, string $file): BinaryFileResponse
    {
        $found = DownloadFile::where(['product' => $product, 'file_name' => $file])->orderByDesc('id')->first();
        abort_unless($found, 404);

        return ReleaseFiles::respond($found);
    }

    /** Anything on the old app.atglance.live host: pages redirect, files are served at their old paths. */
    public function legacy(string $path = ''): BinaryFileResponse|RedirectResponse|Response
    {
        $path = trim($path, '/');
        $site = rtrim(config('atglance.frontend_url'), '/');

        $pages = ['' => '/downloads', 'index.html' => '/downloads', 'cli' => '/downloads#cli', 'cli/index.html' => '/downloads#cli',
            'console' => '/downloads#console', 'console/index.html' => '/downloads#console',
            'sitemap.xml' => '/sitemap.xml', 'robots.txt' => '/robots.txt'];
        if (isset($pages[$path])) {
            return redirect()->away($site.$pages[$path], 301);
        }

        if (basename($path) === 'SHA256SUMS') {
            $files = ReleaseFiles::inLegacyFolder(dirname($path));
            abort_if($files->isEmpty(), 404);

            return $this->sums($files);
        }

        $found = ReleaseFiles::findLegacy($path);
        abort_unless($found, 404);

        return ReleaseFiles::respond($found);
    }

    private function sums($files): Response
    {
        return response(ReleaseFiles::checksums($files), 200, [
            'Content-Type' => 'text/plain; charset=utf-8',
            'Content-Disposition' => 'inline; filename="SHA256SUMS"',
        ]);
    }
}
