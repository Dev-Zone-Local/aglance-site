<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Doc;
use App\Models\Faq;
use App\Models\Page;
use App\Models\PricingPlan;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;

class CmsController extends Controller
{
    public function pricing(): JsonResponse
    {
        return response()->json(PricingPlan::orderBy('order')->get());
    }

    public function faqs(): JsonResponse
    {
        return response()->json(Faq::orderBy('order')->get());
    }

    public function docs(): JsonResponse
    {
        return response()->json(
            Doc::orderBy('order')->get(['id', 'slug', 'title', 'section', 'order'])
        );
    }

    public function doc(string $slug): JsonResponse
    {
        $doc = Doc::where('slug', $slug)->first();
        abort_unless($doc, 404, 'Doc not found');

        return response()->json($doc);
    }

    public function contact(): JsonResponse
    {
        return response()->json(Setting::get(Setting::CONTACT));
    }

    public function page(string $slug): JsonResponse
    {
        $page = Page::where('slug', $slug)->first();
        abort_unless($page, 404, 'Page not found');

        return response()->json($page);
    }

    public function downloads(): JsonResponse
    {
        return response()->json(Setting::get(Setting::DOWNLOADS));
    }
}
