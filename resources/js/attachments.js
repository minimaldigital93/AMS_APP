// Shared multi-file attachment picker: client-side image compression (so
// large iPhone photos don't trip the server's upload-size limit), a
// size/count-limit popup, and a remove-before-submit preview list. Used by
// the Business Expense form and the Tenant create/edit forms.

function formatFileSize(bytes) {
    if (bytes >= 1024 * 1024) {
        return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
    }
    return Math.max(1, Math.round(bytes / 1024)) + ' KB';
}

// Styled popup when the shared confirm-modal partial is on the page,
// native alert() otherwise.
function notify(message) {
    (window.amsAlert || window.alert)(message);
}

// Undo the layout's global submit UX (disabled buttons + spinner). Needed when
// a submit listener blocks the POST *after* the layout's capture-phase handler
// already engaged — without this the form's buttons stay dead.
function restoreSubmitUx(form) {
    delete form.dataset.submitting;
    form.querySelectorAll('button[type="submit"], input[type="submit"]').forEach((btn) => {
        btn.disabled = false;
        btn.removeAttribute('aria-disabled');
        btn.querySelector('.spinner')?.remove();
    });
}

function compressImage(file, maxDimension, quality) {
    return new Promise((resolve) => {
        const img = new Image();
        const objectUrl = URL.createObjectURL(file);

        img.onload = () => {
            URL.revokeObjectURL(objectUrl);

            let { width, height } = img;
            if (width <= maxDimension && height <= maxDimension) {
                resolve(file);
                return;
            }

            const scale = maxDimension / Math.max(width, height);
            width = Math.round(width * scale);
            height = Math.round(height * scale);

            const canvas = document.createElement('canvas');
            canvas.width = width;
            canvas.height = height;
            const ctx = canvas.getContext('2d');
            ctx.drawImage(img, 0, 0, width, height);

            canvas.toBlob((blob) => {
                if (! blob) {
                    resolve(file);
                    return;
                }
                const name = file.name.replace(/\.(heic|heif|png|jpe?g|gif|webp)$/i, '') + '.jpg';
                resolve(new File([blob], name, { type: 'image/jpeg' }));
            }, 'image/jpeg', quality);
        };

        img.onerror = () => {
            URL.revokeObjectURL(objectUrl);
            resolve(file);
        };

        img.src = objectUrl;
    });
}

// Exposed for the "Assign Tenant" quick-action modal, whose file inputs are
// plain (not the Alpine `multiAttachments` component) and driven by an inline
// <script>. Without this, that modal couldn't shrink large iPhone photos and a
// photo over the per-file limit would either be wiped or trip a raw 413.
window.amsCompressImage = compressImage;
window.amsFormatFileSize = formatFileSize;
window.amsRestoreSubmitUx = restoreSubmitUx;

// Belt-and-braces guard: a form can combine multiple file inputs (e.g. a
// tenant's photo + several documents) whose individual limits are each fine,
// but which together can still exceed the server's total request-size cap.
// Catch that client-side with a popup instead of letting the browser POST
// straight into a raw 413 page. Opt in via `data-max-total-bytes` on the
// <form>; requires `enctype="multipart/form-data"`.
document.addEventListener('submit', (event) => {
    const form = event.target;
    if (! (form instanceof HTMLFormElement) || ! form.dataset.maxTotalBytes) {
        return;
    }

    const maxTotalBytes = Number(form.dataset.maxTotalBytes);
    let total = 0;
    form.querySelectorAll('input[type="file"]').forEach((input) => {
        Array.from(input.files ?? []).forEach((file) => {
            total += file.size;
        });
    });

    if (total > maxTotalBytes) {
        event.preventDefault();
        restoreSubmitUx(form);
        const message = form.dataset.maxTotalMessage ?? 'These files are too large to upload together.';
        notify(message
            .replace(':size', formatFileSize(total))
            .replace(':max', formatFileSize(maxTotalBytes)));
    }
});

document.addEventListener('alpine:init', () => {
    Alpine.data('multiAttachments', (opts = {}) => ({
        maxFiles: opts.maxFiles ?? 5,
        maxBytes: opts.maxBytes ?? 10 * 1024 * 1024,
        maxDimension: opts.maxDimension ?? 1920,
        jpegQuality: opts.jpegQuality ?? 0.72,
        tooLargeMessage: opts.tooLargeMessage ?? 'This file is too large.',
        tooManyMessage: opts.tooManyMessage ?? 'Too many files selected.',
        inputRef: null,
        files: [],

        init() {
            this.inputRef = this.$refs.input;
        },

        async onSelect(event) {
            const picked = Array.from(event.target.files ?? []);
            if (picked.length === 0) {
                return;
            }

            let slots = this.maxFiles - this.files.length;
            if (slots <= 0) {
                notify(this.tooManyMessage.replace(':max', this.maxFiles));
                event.target.value = '';
                return;
            }
            if (picked.length > slots) {
                notify(this.tooManyMessage.replace(':max', this.maxFiles));
            }

            for (const original of picked.slice(0, slots)) {
                const file = original.type.startsWith('image/')
                    ? await compressImage(original, this.maxDimension, this.jpegQuality)
                    : original;

                if (file.size > this.maxBytes) {
                    notify(this.tooLargeMessage
                        .replace(':name', original.name)
                        .replace(':size', formatFileSize(file.size))
                        .replace(':max', formatFileSize(this.maxBytes)));
                    continue;
                }

                this.files.push({
                    file,
                    name: file.name,
                    sizeLabel: formatFileSize(file.size),
                    previewUrl: file.type.startsWith('image/') ? URL.createObjectURL(file) : null,
                    isImage: file.type.startsWith('image/'),
                });
            }

            event.target.value = '';
            this.syncInput();
        },

        removeFile(index) {
            const [removed] = this.files.splice(index, 1);
            if (removed?.previewUrl) {
                URL.revokeObjectURL(removed.previewUrl);
            }
            this.syncInput();
        },

        syncInput() {
            const transfer = new DataTransfer();
            this.files.forEach((f) => transfer.items.add(f.file));
            this.inputRef.files = transfer.files;
        },
    }));
});
