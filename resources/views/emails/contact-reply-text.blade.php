Hi {{ $contact->name }},

{!! $body !!}

Thanks,
The AtGlance team

---
Your message {{ $contact->reference() }}: {!! $contact->subject !!}
Reply to this email to continue the conversation.
{{ rtrim(config('atglance.frontend_url'), '/') }}
