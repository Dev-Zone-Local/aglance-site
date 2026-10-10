<?php

namespace App\Http\Controllers;

use App\Models\ContactMessage;
use App\Models\Setting;
use App\Notifications\ContactMessageConfirmation;
use App\Notifications\ContactMessageReceived;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Throwable;

/**
 * The form on /contact. The message is always saved (Admin → Support → Messages); the emails to
 * the support address and the confirmation to the sender are best effort, so a mail outage
 * never loses a message.
 */
class ContactController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        abort_unless(Setting::get(Setting::CONTACT)['show_form'] ?? true, 404);

        // Honeypot: real people never fill the hidden "website" field.
        if (filled($request->input('website'))) {
            return redirect()->route('contact')->with('status', 'Thanks, your message was sent.');
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email:rfc', 'max:255'],
            'topic' => ['required', Rule::in(array_keys(ContactMessage::TOPICS))],
            'product' => ['nullable', Rule::in(array_keys(ContactMessage::PRODUCTS))],
            'subject' => ['required', 'string', 'max:150'],
            'message' => ['required', 'string', 'min:10', 'max:5000'],
        ]);

        $message = ContactMessage::create([
            ...$data,
            'user_id' => $request->user()?->getKey(),
            'ip' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 250, ''),
        ]);

        try {
            Notification::route('mail', ContactMessage::recipient())->notify(new ContactMessageReceived($message));
            $message->forceFill(['emailed_at' => now()])->save();
        } catch (Throwable $e) {
            Log::error('Could not email contact message', ['id' => $message->getKey(), 'error' => $e->getMessage()]);
        }

        try {
            Notification::route('mail', $message->email)->notify(new ContactMessageConfirmation($message));
        } catch (Throwable $e) {
            Log::warning('Could not send contact confirmation', ['id' => $message->getKey(), 'error' => $e->getMessage()]);
        }

        return redirect()->to(route('contact').'#contact-form')
            ->with('status', "Thanks! We received your message {$message->reference()} and will reply by email.")
            ->with('sent_reference', $message->reference());
    }
}
