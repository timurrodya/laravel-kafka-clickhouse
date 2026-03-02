<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Доступность и цены') — {{ config('app.name') }}</title>
    <style>
        :root {
            --bg: #0f1419;
            --surface: #1a2332;
            --border: #2d3a4d;
            --text: #e6edf3;
            --muted: #8b949e;
            --accent: #58a6ff;
            --success: #3fb950;
            --danger: #f85149;
        }
        * { box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', system-ui, sans-serif;
            background: var(--bg);
            color: var(--text);
            margin: 0;
            min-height: 100vh;
            line-height: 1.5;
        }
        .container { max-width: 900px; margin: 0 auto; padding: 2rem 1rem; }
        h1 { font-size: 1.5rem; font-weight: 600; margin: 0 0 1.5rem; color: var(--text); }
        .card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 1.25rem 1.5rem;
            margin-bottom: 1.5rem;
        }
        label { display: block; font-size: 0.875rem; color: var(--muted); margin-bottom: 0.25rem; }
        input, select {
            width: 100%;
            max-width: 280px;
            padding: 0.5rem 0.75rem;
            font-size: 1rem;
            background: var(--bg);
            border: 1px solid var(--border);
            border-radius: 6px;
            color: var(--text);
        }
        input:focus, select:focus {
            outline: none;
            border-color: var(--accent);
        }
        .form-row { display: flex; flex-wrap: wrap; gap: 1rem; align-items: flex-end; margin-bottom: 1rem; }
        .form-group { flex: 0 0 auto; }
        button[type="submit"] {
            padding: 0.5rem 1.25rem;
            font-size: 1rem;
            background: var(--accent);
            color: #fff;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 500;
        }
        button[type="submit"]:hover { filter: brightness(1.1); }
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.9rem;
        }
        th, td { padding: 0.6rem 0.75rem; text-align: left; border-bottom: 1px solid var(--border); }
        th { color: var(--muted); font-weight: 500; }
        .available-yes { color: var(--success); }
        .available-no { color: var(--danger); }
        .alert { padding: 0.75rem 1rem; border-radius: 6px; margin-bottom: 1rem; }
        .alert-error { background: rgba(248,81,73,0.15); color: var(--danger); border: 1px solid var(--danger); }
        .empty { color: var(--muted); padding: 1.5rem; text-align: center; }
        .main-nav { display: flex; gap: 1rem; margin-bottom: 1.5rem; font-size: 0.9rem; }
        .main-nav a { color: var(--accent); text-decoration: none; }
        .main-nav a:hover { text-decoration: underline; }
        .btn {
            display: inline-block;
            padding: 0.5rem 1rem;
            font-size: 0.9rem;
            background: var(--accent);
            color: #fff;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            text-decoration: none;
            font-weight: 500;
        }
        .btn:hover { filter: brightness(1.1); }
        .btn-sm { padding: 0.35rem 0.75rem; font-size: 0.85rem; }
        .btn-secondary { background: var(--border); color: var(--text); }
        .btn-secondary:hover { background: #3d4d66; }
        .btn-danger { background: var(--danger); }
        .btn-danger:hover { filter: brightness(1.1); }
        .form-inline { display: inline; }
        .alert-success { background: rgba(63,185,80,0.15); color: var(--success); border: 1px solid var(--success); }
        .form-error { display: block; font-size: 0.875rem; color: var(--danger); margin-top: 0.25rem; }
        .variant-tag {
            display: inline-block;
            background: var(--bg);
            border: 1px solid var(--border);
            border-radius: 4px;
            padding: 0.2rem 0.5rem;
            margin: 0.15rem 0.15rem 0.15rem 0;
            font-size: 0.85rem;
        }
        .variant-edit { margin-left: 0.35rem; font-size: 0.8rem; color: var(--accent); }
        .variant-delete { display: inline; margin-left: 0.25rem; }
        .btn-link { background: none; border: none; color: var(--muted); cursor: pointer; padding: 0 0.2rem; font-size: 1rem; line-height: 1; }
        .btn-link.btn-danger { color: var(--danger); }
        .btn-link:hover { color: var(--text); }
    </style>
</head>
<body>
    <div class="container">
        <h1>{{ config('app.name') }}</h1>
        <nav class="main-nav">
            <a href="{{ route('availability.index') }}">По размещению</a>
            <a href="{{ route('search.index') }}">Поиск по гостям</a>
            <a href="{{ route('hotels.index') }}">Отели и размещения</a>
            <a href="{{ route('stats.index') }}">Статистика</a>
            <a href="{{ route('api.docs') }}">API Docs</a>
        </nav>
        @yield('content')
    </div>
</body>
</html>
