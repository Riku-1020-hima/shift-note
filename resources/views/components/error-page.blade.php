@props(['code', 'title'])

{{-- エラー画面の共通レイアウト(データベースなどが止まっていても表示できるよう、ほかに頼らない作り) --}}
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }} | シフトノート</title>
    <style>
        :root { --ink: #1f2a37; --paper: #fbf8f3; --brand: #c0521d; --brand-dark: #a14418; --muted: #57534e; --line: #e7e5e4; }
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 24px 16px;
               background: var(--paper); color: var(--ink); font-family: 'Hiragino Sans', 'Hiragino Kaku Gothic ProN', 'Noto Sans JP', 'Yu Gothic', system-ui, sans-serif; line-height: 1.7; }
        main { width: 100%; max-width: 480px; background: #fff; border: 1px solid var(--line); border-radius: 16px; padding: 28px 24px; }
        .code { display: inline-block; font-size: 12px; font-weight: 700; color: var(--brand); background: #fdf3ec; border-radius: 999px; padding: 2px 10px; }
        h1 { font-size: 20px; margin: 12px 0 6px; }
        p { margin: 0 0 12px; color: var(--muted); font-size: 15px; }
        h2 { font-size: 14px; margin: 18px 0 8px; }
        ol { margin: 0 0 4px; padding-left: 1.4em; font-size: 15px; }
        li { margin-bottom: 6px; }
        .note { font-size: 13px; background: #f5f5f4; border-radius: 10px; padding: 10px 12px; margin-top: 14px; }
        .actions { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 20px; }
        .btn { display: inline-block; text-decoration: none; font: inherit; font-size: 15px; font-weight: 700; border-radius: 10px; padding: 10px 18px; cursor: pointer; border: 1px solid var(--line); background: #fff; color: var(--ink); }
        .btn.primary { background: var(--brand); border-color: var(--brand); color: #fff; }
        .btn.primary:hover { background: var(--brand-dark); }
        .btn:focus-visible { outline: 3px solid rgba(192, 82, 29, .35); outline-offset: 2px; }
    </style>
</head>
<body>
<main>
    <span class="code">エラー {{ $code }}</span>
    <h1>{{ $title }}</h1>
    {{ $slot }}
</main>
</body>
</html>
