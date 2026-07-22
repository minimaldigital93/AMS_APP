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

    Never-strand rule (installed PWA): a standalone PWA window has no browser
    chrome, so a *bare* PDF page is a dead end — no back button, no app nav. So no
    control here ever navigates the window to the raw PDF:
      Preview  → the PDF is shown in an <iframe> (desktop). On mobile, where a
                 browser won't render a PDF in a frame, the "Back to system" bar
                 stays put and Download is the reliable way to open/print it in the
                 device's native viewer (which has its own controls).
      Print    → prints the embedded PDF directly (same-origin); if a browser
                 refuses, it falls back to the download URL (an attachment — it
                 saves the file, it does NOT navigate the window away).
      Download → the stored PDF as an attachment.
      Back     → always visible in the sticky bar; returns into the app.

    Vars: $rental $contractNumber
--}}
@php
    $pdfUrl = route('admin.contracts.preview', $rental);
    $downloadUrl = route('admin.contracts.download', $rental);
    $backUrl = route('admin.tenants.show', $rental->tenant_id);
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

        /* Sticky so "Back to system" is reachable at all times, on every device. */
        .bar {
            position: sticky;
            top: 0;
            z-index: 10;
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: .5rem;
            padding: .6rem .9rem;
            background: #0f172a;
            color: #fff;
            box-shadow: 0 2px 8px rgba(0,0,0,.25);
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
        /* The escape hatch — kept visually distinct so it always reads as "leave". */
        .btn-back { background: #10b981; color: #fff; }
        .btn-back:hover { background: #059669; }

        .viewer { flex: 1; min-height: 0; position: relative; }
        .viewer iframe { width: 100%; height: 100%; border: 0; display: block; }

        /* Mobile browsers (notably iOS Safari, and installed PWAs) will not render
           a PDF inside an iframe. Hide the frame there and show the download panel
           instead — Download opens the file in the native PDF viewer (its own
           print/share controls), and the sticky bar's "Back to system" stays put,
           so the user is never stranded on a chrome-less PDF page. */
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
        <button type="button" class="btn btn-ghost" onclick="downloadContract()">
            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
            {{ __('messages.download') }}
        </button>
        <a class="btn btn-back" href="{{ $backUrl }}">
            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
            {{ __('messages.back_to_system') }}
        </a>
    </div>

    <div class="viewer">
        <iframe id="pdf" src="{{ $pdfUrl }}#toolbar=1&view=FitH" title="{{ __('messages.lease_contract') }} {{ $contractNumber }}"></iframe>
        <div class="mobile-hint">
            <p>{{ __('messages.lease_contract') }} · {{ $contractNumber }}</p>
            <button type="button" class="btn btn-primary" onclick="downloadContract()">
                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                {{ __('messages.download') }}
            </button>
            <a class="btn btn-back" href="{{ $backUrl }}">
                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                {{ __('messages.back_to_system') }}
            </a>
        </div>
    </div>

    <script>
        var downloadUrl = @json($downloadUrl);

        // Fetch the PDF as an attachment WITHOUT navigating this window. A plain
        // `location = downloadUrl` (or an <a> the browser chooses to render) would,
        // in a standalone PWA, replace this page with a chrome-less PDF and strand
        // the user with no way back. A synthetic <a download target="_blank"> keeps
        // this window — and its sticky "Back to system" bar — exactly where it is.
        function downloadContract() {
            var a = document.createElement('a');
            a.href = downloadUrl;
            a.download = '';
            a.target = '_blank';
            a.rel = 'noopener';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
        }

        // Print the embedded PDF itself (correctly justified), not this wrapper.
        // Same-origin frame printing works on desktop Chrome/Firefox/Edge; where a
        // browser refuses (or never rendered the frame, e.g. mobile / installed
        // PWA), fall back to a download — which saves the file for the native
        // viewer to print without ever navigating this window away.
        function printContract() {
            var frame = document.getElementById('pdf');
            try {
                frame.contentWindow.focus();
                frame.contentWindow.print();
            } catch (e) {
                downloadContract();
            }
        }
    </script>
</body>
</html>
