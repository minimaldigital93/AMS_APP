{{--
    Shared error page layout.

    Deliberately self-contained: inline CSS, no @vite / compiled-asset or app-layout
    dependency, no DB/session/route lookups. An error page must render even when the
    thing that broke is the build, the session, or the database. Text is plain English
    on purpose so a broken translation cache can never leave a blank/garbled page.
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>@yield('code') · {{ config('app.name', 'AMS') }}</title>
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
            background: #f8fafc;
            color: #1f2937;
            padding: 24px;
        }
        .card { max-width: 420px; width: 100%; text-align: center; }
        .code { font-size: 3.5rem; font-weight: 700; color: #3b82f6; line-height: 1; margin: 0 0 4px; }
        h1 { font-size: 1.25rem; margin: 0 0 8px; }
        p { color: #6b7280; line-height: 1.5; margin: 0 0 24px; }
        .actions { display: flex; gap: 12px; justify-content: center; flex-wrap: wrap; }
        a.btn, button.btn {
            display: inline-block;
            background: #3b82f6;
            color: #fff;
            border: 0;
            border-radius: 8px;
            padding: 10px 20px;
            font-size: 0.95rem;
            text-decoration: none;
            cursor: pointer;
        }
        a.btn:hover, button.btn:hover { background: #2563eb; }
        a.btn.ghost, button.btn.ghost { background: transparent; color: #3b82f6; border: 1px solid #cbd5e1; }
        a.btn.ghost:hover, button.btn.ghost:hover { background: #eff6ff; }
        @media (prefers-color-scheme: dark) {
            body { background: #0f172a; color: #e2e8f0; }
            p { color: #94a3b8; }
            a.btn.ghost, button.btn.ghost { color: #93c5fd; border-color: #334155; }
            a.btn.ghost:hover, button.btn.ghost:hover { background: #1e293b; }
        }
    </style>
</head>
<body>
    <div class="card">
        <p class="code">@yield('code')</p>
        <h1>@yield('title')</h1>
        <p>@yield('message')</p>
        <div class="actions">
            @section('actions')
                <a class="btn" href="{{ url('/') }}">Go to homepage</a>
                <button class="btn ghost" type="button" onclick="location.reload()">Try again</button>
            @show
        </div>
    </div>
</body>
</html>
