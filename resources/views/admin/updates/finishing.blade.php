<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>Finishing update…</title>
    {{-- Self-contained: this page is shown while program files are being replaced. --}}
    <style>
        body { margin: 0; min-height: 100vh; display: grid; place-items: center; font-family: system-ui, -apple-system, 'Segoe UI', sans-serif; color: #fff; background: radial-gradient(60rem 40rem at 50% 35%, #12348f, #0a1d55 55%, #061236); }
        .box { text-align: center; padding: 2rem; max-width: 28rem; }
        .ring { width: 56px; height: 56px; margin: 0 auto 1.5rem; border-radius: 50%; border: 4px solid rgb(255 255 255 / .2); border-top-color: #fbcb5a; animation: spin 1s linear infinite; }
        @keyframes spin { to { transform: rotate(360deg); } }
        @media (prefers-reduced-motion: reduce) { .ring { animation: none; } }
        h1 { font-size: 1.5rem; margin: 0 0 .5rem; }
        p { color: #bacdfd; margin: 0 0 1.5rem; line-height: 1.5; }
        button { font: inherit; font-weight: 600; padding: .75rem 1.5rem; border: 0; border-radius: 8px; background: #fbcb5a; color: #0f172a; cursor: pointer; }
    </style>
</head>
<body>
<div class="box" role="status" aria-live="polite">
    <div class="ring" aria-hidden="true"></div>
    <h1>Installing version {{ $version }}</h1>
    <p>New files are in place. Updating the database and bringing the site back online — this takes a few seconds.</p>
    <form id="finish" method="post" action="{{ route('admin.updates.finish') }}">
        <input type="hidden" name="_token" value="{{ csrf_token() }}">
        <noscript><button type="submit">Finish update</button></noscript>
    </form>
</div>
<script>setTimeout(() => document.getElementById('finish').submit(), 600);</script>
</body>
</html>
