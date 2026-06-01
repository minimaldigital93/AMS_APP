{{--
    Reusable business letterhead for PDFs / printable documents.
    Reads the per-account business info from settings() (see CLAUDE.md: settings
    are per-account). Renders nothing when no business info has been configured,
    so legacy/unconfigured accounts simply fall back to the document's own title.

    Self-contained inline styles so it renders consistently regardless of the
    host document's CSS and inside Dompdf (DejaVu Sans).
--}}
@php
    $bizName    = settings('company_name');
    $bizAddress = settings('company_address');
    $bizPhone   = settings('company_phone');
    $bizEmail   = settings('company_email');

    $bizContact = array_filter([
        $bizAddress ?: null,
        $bizPhone ? __('messages.tel') . ': ' . $bizPhone : null,
        $bizEmail ?: null,
    ]);
@endphp
@if($bizName || $bizContact)
<div style="text-align:center; padding-bottom:12px; margin-bottom:18px; border-bottom:2px solid #1e40af;">
    @if($bizName)
        <div style="font-size:18px; font-weight:700; color:#1e40af; letter-spacing:0.3px;">{{ $bizName }}</div>
    @endif
    @if(count($bizContact))
        <div style="font-size:11px; color:#6b7280; margin-top:4px;">{{ implode('  ·  ', $bizContact) }}</div>
    @endif
</div>
@endif
