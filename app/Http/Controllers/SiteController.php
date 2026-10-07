<?php

namespace App\Http\Controllers;

use App\Models\Doc;
use App\Models\Faq;
use App\Models\Page;
use App\Models\PricingPlan;
use App\Models\Setting;
use App\Support\Downloads;
use App\Support\ProductShowcase;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/** Public marketing pages, docs and CMS pages (Blade views in resources/views/site). */
class SiteController extends Controller
{
    public function home(): View
    {
        return view('site.home');
    }

    public function product(): View
    {
        return view('site.product');
    }

    public function cli(): View
    {
        return view('site.cli', ['page' => ProductShowcase::get('cli'), 'latest' => Downloads::all()['cli']['releases'][0] ?? null]);
    }

    public function console(): View
    {
        return view('site.console', ['page' => ProductShowcase::get('console'), 'latest' => Downloads::all()['console']['releases'][0] ?? null]);
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

    public function pricing(): View
    {
        return view('site.pricing', ['plans' => PricingPlan::orderBy('order')->get()]);
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

    public function contact(): View
    {
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
