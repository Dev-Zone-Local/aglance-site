<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\Doc;
use App\Models\Faq;
use App\Models\Page;
use App\Models\PricingPlan;
use App\Models\Setting;
use App\Models\User;
use App\Support\Downloads;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

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

    /** Signed link from release emails: stop update emails for this user. */
    public function unsubscribe(Request $request, int $id): RedirectResponse
    {
        $dashboard = rtrim(config('atglance.frontend_url'), '/').'/dashboard';

        if (! $request->hasValidSignature()) {
            return redirect()->away($dashboard.'?unsubscribed=invalid');
        }

        User::whereKey($id)->update(['notify_updates' => false]);

        return redirect()->away($dashboard.'?unsubscribed=1');
    }

    /** Dashboard toggle for release emails. */
    public function updatePreferences(Request $request): JsonResponse
    {
        $data = $request->validate(['notify_updates' => ['required', 'boolean']]);
        $request->user()->forceFill(['notify_updates' => $data['notify_updates']])->save();

        return response()->json(['notify_updates' => $request->user()->notify_updates]);
    }

    /** Profile page: change display name. */
    public function updateProfile(Request $request): UserResource
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:255']]);
        $request->user()->forceFill(['name' => trim($data['name'])])->save();

        return new UserResource($request->user());
    }

    /**
     * Profile page: change password. Needs the current password when the account has one
     * (GitHub-only accounts can set a first password). Signs out other sessions.
     */
    public function updatePassword(Request $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validate([
            'current_password' => [$user->password ? 'required' : 'nullable', 'string'],
            'password' => ['required', 'string', 'min:6', 'max:128', 'confirmed'],
        ], ['password.confirmed' => 'Passwords do not match']);

        if ($user->password && ! Hash::check($data['current_password'], $user->password)) {
            throw ValidationException::withMessages(['current_password' => 'The current password is incorrect.']);
        }

        $user->forceFill(['password' => $data['password']])->save();
        DB::table('sessions')->where('user_id', $user->id)->where('id', '!=', $request->session()->getId())->delete();

        return response()->json(['ok' => true, 'message' => 'Password updated.']);
    }

    public function downloads(): JsonResponse
    {
        return response()->json(Downloads::payload());
    }
}
