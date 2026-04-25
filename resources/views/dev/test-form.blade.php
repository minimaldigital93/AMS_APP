<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Dev Test Form</title>
    <style>
        .spinner { border: 2px solid rgba(0,0,0,0.08); border-top-color: rgba(59,130,246,1); border-radius: 9999px; width: 1rem; height: 1rem; display: inline-block; vertical-align: middle; margin-right: 0.5rem; animation: spin 0.9s linear infinite; }
        @keyframes spin { to { transform: rotate(360deg); } }
        button[disabled] { opacity: 0.7; }
    </style>
</head>
<body style="font-family: system-ui, -apple-system, 'Segoe UI', Roboto, 'Helvetica Neue', Arial; padding:2rem;">
    <h1>Dev Test Form</h1>

    @if(session('success'))
        <div style="background:#ecfccb;padding:0.5rem 1rem;border-radius:6px;margin-bottom:1rem;">{{ session('success') }}</div>
    @endif

    <form action="/dev/test-submit" method="post" style="max-width:420px;">
        <input type="hidden" name="_token" value="{{ csrf_token() }}" />
        <label style="display:block;margin-bottom:0.5rem;">Name
            <input name="name" type="text" placeholder="Your name" style="display:block;width:100%;padding:0.5rem;margin-top:0.25rem;border:1px solid #e5e7eb;border-radius:6px;" />
        </label>
        <button type="submit" style="background:#0ea5e9;color:white;padding:0.6rem 1rem;border-radius:6px;border:none;cursor:pointer;font-weight:600;">Submit</button>
    </form>

    <script>
        document.addEventListener('submit', function (e) {
            try {
                var form = e.target;
                if (!(form instanceof HTMLFormElement)) return;
                if (form.dataset.submitting) return;
                form.dataset.submitting = '1';
                var submitter = e.submitter || form.querySelector('button[type="submit"], input[type="submit"]');
                form.querySelectorAll('button[type="submit"], input[type="submit"]').forEach(function (btn) { btn.disabled = true; btn.setAttribute('aria-disabled','true'); });
                if (submitter) {
                    if (!submitter.querySelector('.spinner')) {
                        var spinner = document.createElement('span'); spinner.className = 'spinner'; spinner.setAttribute('aria-hidden','true'); submitter.prepend(spinner);
                    }
                }
            } catch (err) { console.error(err); }
        }, { capture: true });
    </script>
</body>
</html>