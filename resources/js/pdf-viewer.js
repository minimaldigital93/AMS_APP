// Draws a PDF onto canvases inside the page, so a generated document can be
// read in the app itself. A PDF in an <iframe> is no answer on a phone: Chrome
// on Android shows nothing, iOS shows only the first page, and an installed
// app opening the file directly is left with no way back. The legacy build
// keeps older iOS Safari working.
import * as pdfjsLib from 'pdfjs-dist/legacy/build/pdf.mjs';
import workerUrl from 'pdfjs-dist/legacy/build/pdf.worker.min.mjs?url';

// Vite bakes `workerUrl` root-relative (/build/…), which misses the /ams_app
// proxy prefix; resolve the file next to this chunk's own URL instead.
pdfjsLib.GlobalWorkerOptions.workerSrc = new URL(workerUrl.split('/').pop(), import.meta.url).href;

/**
 * Render every page of `data` (ArrayBuffer/Uint8Array) into `container`,
 * fitted to its width. Resolves with the page count.
 */
export async function renderPdf(container, data) {
    // pdf.js hands the buffer to its worker, which detaches it — work on a
    // copy so the caller's bytes stay usable for download/share.
    const pdf = await pdfjsLib.getDocument({ data: new Uint8Array(data).slice() }).promise;
    container.replaceChildren();

    const available = Math.max(container.clientWidth - 24, 280);
    const ratio = Math.min(window.devicePixelRatio || 1, 2);

    for (let n = 1; n <= pdf.numPages; n++) {
        const page = await pdf.getPage(n);
        const natural = page.getViewport({ scale: 1 });
        const cssWidth = Math.min(available, natural.width * 1.6);
        const viewport = page.getViewport({ scale: (cssWidth / natural.width) * ratio });

        const canvas = document.createElement('canvas');
        canvas.width = Math.floor(viewport.width);
        canvas.height = Math.floor(viewport.height);
        canvas.setAttribute('aria-label', 'Page ' + n);
        Object.assign(canvas.style, {
            display: 'block',
            width: cssWidth + 'px',
            maxWidth: '100%',
            height: 'auto',
            margin: '0 auto 12px',
            background: '#fff',
            boxShadow: '0 1px 4px rgba(15, 23, 42, 0.18)',
        });
        container.appendChild(canvas);

        await page.render({ canvasContext: canvas.getContext('2d'), viewport }).promise;
    }

    return pdf.numPages;
}
