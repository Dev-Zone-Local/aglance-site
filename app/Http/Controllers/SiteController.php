<?php

namespace App\Http\Controllers;

use App\Models\Doc;
use App\Models\Faq;
use App\Models\KnownIssue;
use App\Models\Page;
use App\Models\PricingPlan;
use App\Models\Setting;
use App\Support\Content;
use App\Support\Downloads;
use App\Support\ReleaseFiles;
use App\Support\ProductShowcase;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/** Public marketing pages, docs and CMS pages (Blade views in resources/views/site). */
class SiteController extends Controller
{
    public function home(): View
    {
        $all = Downloads::all();

        return view('site.home', ['latest' => array_filter([
            'cli' => $all['cli']['releases'][0] ?? null,
            'console' => $all['console']['releases'][0] ?? null,
        ])]);
    }

    public function product(): View
    {
        return view('site.product');
    }

    public function cli(): View
    {
        $all = Downloads::resolved();

        return view('site.cli', [
            'page' => ProductShowcase::get('cli'),
            'latest' => $all['cli']['releases'][0] ?? null,
            'files' => ReleaseFiles::latest('cli'),
            'commands' => Content::json('cli-commands'),
        ]);
    }

    public function console(): View
    {
        $all = Downloads::resolved();

        return view('site.console', [
            'page' => ProductShowcase::get('console'),
            'latest' => $all['console']['releases'][0] ?? null,
            'platforms' => $all['console']['platforms'] ?? [],
        ]);
    }

    public function architecture(): View
    {
        return view('site.architecture');
    }

    public function useCases(): View
    {
        return view('site.use-cases');
    }

    public function security(): View
    {
        return view('site.security');
    }

    public function remoteTunnel(): View
    {
        return view('site.features.remote-tunnel');
    }

    public function pricing(): View
    {
        return view('site.pricing', [
            'plans' => PricingPlan::orderBy('order')->get(),
            'premiumFeatureNames' => array_map(fn ($n) => mb_strtolower((string) $n), array_values(\App\Models\FeatureCatalog::premiumOptions())),
            'premiumFeatures' => \App\Models\FeatureCatalog::query()
                ->where('tier', \App\Models\FeatureCatalog::TIER_PREMIUM)
                ->whereIn('key', \App\Models\FeatureCatalog::premiumKeys())
                ->orderBy('sort')->get(['key', 'name', 'description'])->toArray(),
        ]);
    }

    public function faq(): View
    {
        return view('site.faq', ['groups' => Faq::orderBy('order')->get()->groupBy('category')]);
    }

    public function releases(Request $request): View
    {
        $product = $request->string('product')->toString();
        $product = isset(Downloads::PRODUCTS[$product]) ? $product : null;

        return view('site.releases', [
            'releases' => Downloads::changelog($product),
            'product' => $product,
            'products' => Downloads::PRODUCTS,
            'types' => Downloads::RELEASE_TYPES,
        ]);
    }

    /** RSS 2.0 feed of every release, newest first. */
    public function releasesFeed(): \Illuminate\Http\Response
    {
        return response()->view('site.releases-feed', ['releases' => array_slice(Downloads::changelog(), 0, 30)])
            ->header('Content-Type', 'application/rss+xml; charset=utf-8');
    }

    /** Roadmap from content/roadmap.json: Planned, In progress, Released. */
    public function roadmap(Request $request): View
    {
        $data = Content::json('roadmap');
        $product = in_array($request->query('product'), ['cli', 'console'], true) ? $request->query('product') : null;
        $items = array_values(array_filter($data['items'] ?? [], fn ($i) => ! $product || in_array($i['product'] ?? 'both', [$product, 'both'], true)));
        $columns = ['planned' => 'Planned', 'in_progress' => 'In progress', 'released' => 'Released'];
        $byStatus = array_fill_keys(array_keys($columns), []);
        foreach ($items as $item) {
            $status = isset($columns[$item['status'] ?? '']) ? $item['status'] : 'planned';
            $item['release_url'] = ! empty($item['release']) && in_array($item['product'], ['cli', 'console'], true) && Downloads::find($item['product'], $item['release'])
                ? Downloads::releaseUrl($item['product'], $item['release']) : null;
            $byStatus[$status][] = $item;
        }

        return view('site.roadmap', ['columns' => $columns, 'byStatus' => $byStatus, 'product' => $product, 'updated' => $data['updated'] ?? null]);
    }

    /** One release in full: notes, checksum, install link, newer / older versions. */
    public function release(string $product, string $version): View
    {
        $found = Downloads::find($product, $version);
        abort_unless($found, 404);

        return view('site.release', [...$found, 'types' => Downloads::RELEASE_TYPES, 'files' => ReleaseFiles::forRelease($product, $version)]);
    }

    public function knownProblems(Request $request): View
    {
        $product = $request->string('product')->toString();
        $product = isset(KnownIssue::PRODUCTS[$product]) ? $product : null;

        return view('site.known-problems', [
            'issues' => KnownIssue::published()->when($product, fn ($q) => $q->where('product', $product))->get(),
            'product' => $product,
            'products' => KnownIssue::PRODUCTS,
            'counts' => KnownIssue::where('is_published', true)->selectRaw('product, count(*) as n')->groupBy('product')->pluck('n', 'product')->all(),
        ]);
    }

    public function contact(Request $request): View
    {
        // Guests see a sign-in prompt instead of the form; bring them back here after signing in.
        if (! $request->user()) {
            redirect()->setIntendedUrl(route('contact').'#contact-form');
        }

        return view('site.contact', ['contact' => Setting::get(Setting::CONTACT) ?? []]);
    }

    public function page(string $slug): View
    {
        $page = Page::where('slug', $slug)->firstOrFail();

        return view('site.page', ['page' => $page]);
    }

    public function docs(): View
    {
        $docs = Doc::orderBy('order')->get(['slug', 'title', 'section']);

        return view('site.docs', ['docs' => $docs, 'groups' => $docs->groupBy('section')]);
    }

    public function doc(string $slug): View
    {
        $doc = Doc::where('slug', $slug)->firstOrFail();

        return view('site.doc', [
            'doc' => $doc,
            'docs' => Doc::orderBy('order')->get(['slug', 'title', 'section']),
        ]);
    }
}
