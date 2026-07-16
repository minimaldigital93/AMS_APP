{{--
    Printable report footer — closes every printed document.

    Shows who generated the document and when. Page numbers ("Page X / Y") are
    added by the @page margin box in resources/css/print.css on browsers that
    support it. Inline styles only, so it also renders inside Dompdf.

    Props:
      screen — also show on screen (standalone print-preview documents).
--}}
@props(['screen' => false])

<div {{ $attributes->class(['print-only' => ! $screen]) }}
     style="margin-top:20px; padding-top:8px; border-top:1px solid #e5e7eb; text-align:center; font-size:9px; color:#9ca3af;">
    {{ settings('company_name') ?: __('messages.apartment_management_system') }}
    &nbsp;·&nbsp; {{ __('messages.generated_by') }}: {{ auth()->user()?->name ?? __('messages.ams') }}
    &nbsp;·&nbsp; {{ now()->format('d M Y · h:i A') }}
    &nbsp;·&nbsp; {{ __('messages.ams') }}
</div>
