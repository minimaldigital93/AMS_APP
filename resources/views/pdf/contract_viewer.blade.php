{{--
    Cross-device "view + print" page for a rental contract.

    Why this exists: the contract itself is rendered by mPDF (App\Services\Pdf\
    KhmerPdf), which shapes AND justifies Khmer correctly. A browser rendering the
    contract HTML directly cannot justify Khmer — the script has no inter-word
    spaces, and no CSS makes Chrome/Safari fill the line — so the paragraphs come
    out ragged. We must therefore show the already-justified PDF, not browser HTML.

    The problem this file solves: iOS Safari and installed PWAs will NOT render a
    PDF inside an <iframe>/<embed>, so on iPad/phone the old preview was a dead
    end — the user only saw the toolbar. We now render the stored PDF with pdf.js
    onto <canvas> elements, which works identically on desktop, iPad and phone
    (including a standalone PWA) because it is plain canvas drawing, no native PDF
    plugin. This gives a real, scrollable, on-screen preview everywhere — the same
    "show it, then print it" experience as the fiscal-period reports.

    Preview  → every page drawn to a canvas, high-res so it also prints crisply.
    Print    → window.print(); @media print shows only the rendered pages, one
               per sheet (the PDF's own A4 margins are baked into each page image).
               If a browser/PWA can't print (older iOS standalone), the user still
               has Download / Open PDF to print from the native viewer.
    Download → the stored PDF as an attachment (never navigates this window away,
               so an installed PWA is never stranded on a chrome-less PDF page).
    Back     → always visible in the sticky bar; returns into the app.

    pdf.js is vendored at public/vendor/pdfjs (see its README) and loaded only on
    this page, so it never bloats the main app bundle.

    Vars: $rental $contractNumber
--}}
@php
    $pdfUrl = route('admin.contracts.preview', $rental);
    $downloadUrl = route('admin.contracts.download', $rental);
    $backUrl = route('admin.tenants.show', $rental->tenant_id);
    // Vendored as .js (not .mjs) so nginx/PHP serve them with a JavaScript MIME
    // type — a .mjs served as application/octet-stream makes the browser refuse
    // to execute the ES module. They are still ES modules (loaded via import()).
    $pdfjsUrl = asset('vendor/pdfjs/pdf.min.js');
    $pdfjsWorkerUrl = asset('vendor/pdfjs/pdf.worker.min.js');
@endphp
<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    {{-- The contract is a Khmer legal document regardless of app locale. --}}
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
        .btn:disabled { opacity: .5; cursor: default; }
        .btn svg { width: 1.05em; height: 1.05em; }
        .btn-primary { background: #2563eb; color: #fff; }
        .btn-primary:hover { background: #1d4ed8; }
        .btn-ghost { background: rgba(255,255,255,.12); color: #fff; }
        .btn-ghost:hover { background: rgba(255,255,255,.22); }
        /* The escape hatch — kept visually distinct so it always reads as "leave". */
        .btn-back { background: #10b981; color: #fff; }
        .btn-back:hover { background: #059669; }

        /* Scrollable page stack — the actual on-screen preview. */
        .viewer {
            flex: 1;
            min-height: 0;
            overflow: auto;
            -webkit-overflow-scrolling: touch;
            padding: 1rem;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 1rem;
        }
        .page {
            width: 100%;
            max-width: 820px;
            background: #fff;
            box-shadow: 0 4px 18px rgba(0,0,0,.35);
        }
        /* pdf.js draws at device resolution; CSS scales the bitmap to fit width. */
        .page canvas { display: block; width: 100%; height: auto; }

        .state {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 1rem;
            margin: auto;
            padding: 2rem 1.5rem;
            text-align: center;
            color: #e2e8f0;
        }
        .state p { margin: 0; font-size: .95rem; line-height: 1.6; }
        .spinner {
            width: 2.25rem;
            height: 2.25rem;
            border: 3px solid rgba(255,255,255,.25);
            border-top-color: #fff;
            border-radius: 50%;
            animation: spin .8s linear infinite;
        }
        @keyframes spin { to { transform: rotate(360deg); } }
        [hidden] { display: none !important; }

        /* Print: show ONLY the rendered pages, one page image per sheet. Each
           canvas already carries the PDF's own A4 margins, so the sheet margin
           is 0 to avoid doubling them. */
        @media print {
            @page { size: A4; margin: 0; }
            html, body { background: #fff; height: auto; }
            .bar, .state { display: none !important; }
            .viewer {
                overflow: visible;
                padding: 0;
                display: block;
            }
            .page {
                max-width: none;
                width: 100%;
                box-shadow: none;
                break-inside: avoid;
                page-break-inside: avoid;
                break-after: page;
                page-break-after: always;
            }
            .page:last-child { break-after: auto; page-break-after: auto; }
            .page canvas { width: 100%; height: auto; }
        }
    </style>
</head>
<body>
    <div class="bar">
        <span class="doc">
            <span class="name">{{ __('messages.lease_contract') }}</span>
            <span class="num">{{ $contractNumber }}</span>
        </span>

        <button type="button" id="printBtn" class="btn btn-primary" onclick="printContract()" disabled>
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

    <div class="viewer" id="viewer">
        {{-- Rendered <canvas> pages are appended here. --}}
        <div class="state" id="loading">
            <div class="spinner"></div>
            <p>{{ __('messages.loading_preview') }}</p>
        </div>
        <div class="state" id="failed" hidden>
            <p>{{ __('messages.preview_unavailable') }}</p>
            <div style="display:flex; gap:.6rem; flex-wrap:wrap; justify-content:center;">
                <button type="button" class="btn btn-primary" onclick="downloadContract()">
                    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                    {{ __('messages.download') }}
                </button>
                <a class="btn btn-ghost" href="{{ $pdfUrl }}" target="_blank" rel="noopener">
                    {{ __('messages.open_pdf') }}
                </a>
            </div>
        </div>
    </div>

    <script type="module">
        const PDF_URL = @json($pdfUrl);
        const DOWNLOAD_URL = @json($downloadUrl);
        const PDFJS_URL = @json($pdfjsUrl);
        const WORKER_URL = @json($pdfjsWorkerUrl);

        // Fetch the stored PDF as an attachment WITHOUT navigating this window. A
        // plain `location = DOWNLOAD_URL` would, in a standalone PWA, replace this
        // page with a chrome-less PDF and strand the user with no way back. A
        // synthetic <a download target="_blank"> keeps this window (and its sticky
        // "Back to system" bar) exactly where it is.
        function downloadContract() {
            const a = document.createElement('a');
            a.href = DOWNLOAD_URL;
            a.download = '';
            a.target = '_blank';
            a.rel = 'noopener';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
        }

        // Print the on-screen rendered pages (correctly justified — they ARE the
        // mPDF output). @media print isolates the canvases to one-per-sheet. Where
        // a browser/PWA can't drive window.print() (older iOS standalone), the
        // Download / Open PDF controls remain for the native viewer to print.
        function printContract() {
            window.focus();
            window.print();
        }

        // Expose for the inline onclick handlers (module scope is not global).
        window.downloadContract = downloadContract;
        window.printContract = printContract;

        const loadingEl = document.getElementById('loading');
        const failedEl = document.getElementById('failed');
        const viewerEl = document.getElementById('viewer');
        const printBtn = document.getElementById('printBtn');

        function showFailed() {
            loadingEl.hidden = true;
            failedEl.hidden = false;
        }

        // Render every page at ~2x CSS width so the preview is sharp on retina and
        // the same canvas prints crisply on A4. Capped so a big screen doesn't
        // allocate an enormous bitmap per page.
        async function renderPdf() {
            let pdfjsLib;
            try {
                pdfjsLib = await import(PDFJS_URL);
            } catch (e) {
                console.error('[contract] pdf.js failed to load', e);
                showFailed();
                return;
            }

            pdfjsLib.GlobalWorkerOptions.workerSrc = WORKER_URL;

            let pdf;
            try {
                pdf = await pdfjsLib.getDocument({ url: PDF_URL, withCredentials: true }).promise;
            } catch (e) {
                console.error('[contract] PDF could not be opened', e);
                showFailed();
                return;
            }

            const targetCssWidth = Math.min(viewerEl.clientWidth - 32, 820);
            const dpr = Math.min(window.devicePixelRatio || 1, 2);

            for (let n = 1; n <= pdf.numPages; n++) {
                let page;
                try {
                    page = await pdf.getPage(n);
                } catch (e) {
                    if (n === 1) { showFailed(); return; }
                    break; // keep whatever rendered so far
                }

                const base = page.getViewport({ scale: 1 });
                const scale = (targetCssWidth / base.width) * dpr;
                const viewport = page.getViewport({ scale });

                const canvas = document.createElement('canvas');
                canvas.width = Math.floor(viewport.width);
                canvas.height = Math.floor(viewport.height);

                const wrap = document.createElement('div');
                wrap.className = 'page';
                wrap.appendChild(canvas);
                viewerEl.insertBefore(wrap, loadingEl);

                await page.render({ canvasContext: canvas.getContext('2d'), viewport }).promise;

                // Reveal controls as soon as the first page is on screen.
                if (n === 1) {
                    loadingEl.hidden = true;
                    printBtn.disabled = false;
                }
            }
        }

        renderPdf();
    </script>
</body>
</html>
