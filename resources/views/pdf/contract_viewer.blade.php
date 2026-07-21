{{--
    Cross-device "view + print" page for a rental contract.

    Why this exists: the contract itself is rendered by mPDF (App\Services\Pdf\
    KhmerPdf), which shapes AND justifies Khmer correctly. A browser rendering the
    contract HTML directly cannot justify Khmer — the script has no inter-word
    spaces, and no CSS (text-align:justify, text-justify:inter-character) makes
    Chrome/Safari fill the line, so the paragraphs come out ragged. So instead of
    printing browser HTML, this lightweight wrapper embeds the already-justified
    PDF for preview and prints THAT, giving identical, correctly-justified output
    on every device.

    Preview      → the PDF is shown in an <iframe> (desktop) / the "Open" button
                   hands off to the device's native PDF viewer (mobile).
    Print        → prints the embedded PDF directly (same-origin), falling back to
                   opening the PDF when a browser refuses to print the frame.
    Download     → the stored PDF as an attachment.

    Vars: $rental $contractNumber
--}}
@php
    $pdfUrl = route('admin.contracts.preview', $rental);
@endphp
<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>កិច្ចសន្យាជួលបន្ទប់ — {{ $contractNumber }}</title>
    @include('partials.khmer_fonts', ['forPdf' => false])
    <style>
        * { box-sizing: border-box; }
        html, body { margin: 0; height: 100%; }
        body {
            display: flex;
            flex-direction: column;
            background: #525659;
            font-family: 'Khmer OS Siemreap', system-ui, -apple-system, Segoe UI, sans-serif;
        }

        .bar {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: .5rem;
            padding: .6rem .9rem;
            background: #0f172a;
            color: #fff;
        }
        .bar .doc {
            margin-right: auto;
            min-width: 0;
            display: flex;
            flex-direction: column;
            line-height: 1.2;
        }
        .bar .doc .name { font-weight: 600; font-size: .95rem; }
        .bar .doc .num  { font-size: .75rem; color: #94a3b8; }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: .4rem;
            padding: .5rem .85rem;
            border: 0;
            border-radius: .55rem;
            font: inherit;
            font-size: .85rem;
            font-weight: 600;
            text-decoration: none;
            cursor: pointer;
            white-space: nowrap;
        }
        .btn svg { width: 1.05em; height: 1.05em; }
        .btn-primary { background: #2563eb; color: #fff; }
        .btn-primary:hover { background: #1d4ed8; }
        .btn-ghost { background: rgba(255,255,255,.12); color: #fff; }
        .btn-ghost:hover { background: rgba(255,255,255,.22); }

        .viewer { flex: 1; min-height: 0; position: relative; }
        .viewer iframe { width: 100%; height: 100%; border: 0; display: block; }

        /* Mobile browsers (notably iOS Safari) will not render a PDF inside an
           iframe. Hide the frame there and show the "open in the PDF viewer"
           panel instead, which can preview, print and share the file. */
        .mobile-hint { display: none; }
        @media (max-width: 640px), (hover: none) and (pointer: coarse) {
            .viewer iframe { display: none; }
            .mobile-hint {
                display: flex;
                flex-direction: column;
                align-items: center;
                justify-content: center;
                gap: 1rem;
                height: 100%;
                padding: 2rem 1.5rem;
                text-align: center;
                color: #e2e8f0;
            }
            .mobile-hint p { margin: 0; font-size: .95rem; line-height: 1.6; }
        }
    </style>
</head>
<body>
    <div class="bar">
        <span class="doc">
            <span class="name">{{ __('messages.lease_contract') }}</span>
            <span class="num">{{ $contractNumber }}</span>
        </span>

        <button type="button" class="btn btn-primary" onclick="printContract()">
            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 9V2h12v7M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0 01-2 2h-2M6 14h12v8H6v-8z"/></svg>
            {{ __('messages.print') }}
        </button>
        <a class="btn btn-ghost" href="{{ route('admin.contracts.download', $rental) }}">
            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
            {{ __('messages.download') }}
        </a>
        <a class="btn btn-ghost" href="{{ $pdfUrl }}" target="_blank" rel="noopener">
            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
            {{ __('messages.view') }}
        </a>
    </div>

    <div class="viewer">
        <iframe id="pdf" src="{{ $pdfUrl }}#toolbar=1&view=FitH" title="{{ __('messages.lease_contract') }} {{ $contractNumber }}"></iframe>
        <div class="mobile-hint">
            <p>{{ __('messages.lease_contract') }} · {{ $contractNumber }}</p>
            <a class="btn btn-primary" href="{{ $pdfUrl }}" target="_blank" rel="noopener">
                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                {{ __('messages.preview') }} / {{ __('messages.print') }}
            </a>
        </div>
    </div>

    <script>
        var pdfUrl = @json($pdfUrl);

        // Print the embedded PDF itself (correctly justified), not this wrapper.
        // Same-origin frame printing works on desktop Chrome/Firefox/Edge; where a
        // browser refuses (or never rendered the frame, e.g. mobile), open the PDF
        // so the native viewer can print/share it.
        function printContract() {
            var frame = document.getElementById('pdf');
            try {
                frame.contentWindow.focus();
                frame.contentWindow.print();
            } catch (e) {
                window.open(pdfUrl, '_blank', 'noopener');
            }
        }
    </script>
</body>
</html>
