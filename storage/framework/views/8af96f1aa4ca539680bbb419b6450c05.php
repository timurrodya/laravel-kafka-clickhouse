<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $__env->yieldContent('title', 'Доступность и цены'); ?> — <?php echo e(config('app.name')); ?></title>
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
    </style>
</head>
<body>
    <div class="container">
        <h1><?php echo e(config('app.name')); ?></h1>
        <?php echo $__env->yieldContent('content'); ?>
    </div>
</body>
</html>
<?php /**PATH /var/www/html/resources/views/layouts/app.blade.php ENDPATH**/ ?>