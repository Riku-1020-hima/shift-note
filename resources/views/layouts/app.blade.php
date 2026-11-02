<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @php
        $appName = auth()->user()?->display_app_name ?? \App\Models\User::DEFAULT_APP_NAME;
        $tourKey = request()->route()?->getName();
        // スタッフ用のページ($staffPage = true)では、店長用のメニューを一切出さない
        $managerView = auth()->check() && empty($staffPage);
        $tourSteps = $managerView ? (config('tour')[$tourKey] ?? []) : [];
    @endphp
    <title>{{ isset($title) ? $title.' | ' : '' }}{{ $appName }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Zen+Kaku+Gothic+New:wght@400;500;700&display=swap" rel="stylesheet">
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        {{-- 公開用:npm run build で作った CSS を読み込む(色やフォントは resources/css/app.css) --}}
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @else
        {{-- まだ npm run build していないときの予備(開発中だけ。公開前に必ずビルドする) --}}
        <script src="https://cdn.tailwindcss.com/3.4.16"></script>
        <script>
            console.warn('CSS がまだビルドされていません。npm install && npm run build を実行してください。');
            tailwind.config = { theme: { extend: {
                fontFamily: { sans: ['"Zen Kaku Gothic New"', 'system-ui', 'sans-serif'] },
                colors: {
                    ink: '#1f2a37', paper: '#fbf8f3',
                    brand: { 50: '#fdf3ec', 100: '#f9e0cf', 500: '#c0521d', 600: '#a14418', 700: '#7f3615' },
                    alert: { 50: '#fdf0f0', 500: '#c53b3b', 600: '#a52f2f' },
                },
            } } };
        </script>
    @endif
</head>
<body class="bg-paper text-ink font-sans antialiased min-h-screen">
@if ($managerView)
    <header class="bg-white border-b border-stone-200">
        <div class="max-w-6xl mx-auto px-4 flex items-center justify-between h-14">
            <a href="{{ route('dashboard') }}" class="flex items-center gap-2 font-bold text-brand-700">
                <x-app-logo />
                <span class="leading-tight">{{ $appName }}<span class="hidden sm:inline text-stone-400 font-normal text-sm ml-2">{{ auth()->user()->name }}</span></span>
            </a>
            <div class="flex items-center gap-3 sm:gap-4 text-sm">
                @if (! empty($tourSteps))
                    <button type="button" data-tour="help" onclick="startTour(true)" class="inline-flex items-center gap-1 text-stone-500 hover:text-ink">
                        <span class="inline-flex w-5 h-5 rounded-full border border-current items-center justify-center text-xs font-bold">?</span><span class="hidden sm:inline">使い方</span>
                    </button>
                @endif
                <a href="{{ route('settings.edit') }}" class="{{ request()->routeIs('settings.*') ? 'text-brand-700 font-bold' : 'text-stone-500 hover:text-ink' }}">設定</a>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button class="text-stone-500 hover:text-ink">ログアウト</button>
                </form>
            </div>
        </div>
        <nav data-tour="nav" class="max-w-6xl mx-auto px-4 flex gap-1 overflow-x-auto text-sm">
            @php
                $nav = [
                    ['dashboard', 'ホーム', 'dashboard'],
                    ['shifts.index', 'シフト表', 'shifts.*'],
                    ['leaves.index', '有給', 'leaves.*'],
                    ['staff.index', 'スタッフ', 'staff.*'],
                    ['slots.index', '時間帯', 'slots.*'],
                ];
                // 承認待ちの有給の数(メニューに小さく出す)
                $pendingLeaveBadge = \App\Models\LeaveRequest::whereIn('staff_id', auth()->user()->staff()->select('id'))->status('pending')->count();
            @endphp
            @foreach ($nav as [$route, $label, $pattern])
                <a href="{{ route($route) }}"
                   class="px-3 py-2.5 whitespace-nowrap border-b-2 {{ request()->routeIs($pattern) ? 'border-brand-500 text-brand-700 font-bold' : 'border-transparent text-stone-500 hover:text-ink' }}">
                    {{ $label }}
                    @if ($route === 'leaves.index' && $pendingLeaveBadge)
                        <span class="ml-0.5 inline-flex min-w-5 h-5 px-1 items-center justify-center rounded-full bg-alert-500 text-white text-xs font-bold" aria-label="承認待ち{{ $pendingLeaveBadge }}件">{{ $pendingLeaveBadge }}</span>
                    @endif
                </a>
            @endforeach
        </nav>
    </header>
@endif

<main class="max-w-6xl mx-auto px-4 py-6">
    @if (config('demo.enabled') && $managerView)
        <div class="mb-5 rounded-lg border border-amber-200 bg-amber-50 text-amber-900 px-4 py-2.5 text-sm">
            デモ版です。入力した内容は毎日{{ (int) config('demo.reset_at') }}時(日本時間)に消えて、元のデータに戻ります。本物の個人情報は入れないでください。
        </div>
    @endif
    @if (session('status'))
        <div class="mb-5 rounded-lg bg-brand-50 border border-brand-100 text-brand-700 px-4 py-3 text-sm">{{ session('status') }}</div>
    @endif

    @yield('content')
</main>

<script>
    // data-copy の文章をコピーする(「URLをコピー」「催促文をコピー」など)
    function copyText(btn) {
        const text = btn.dataset.copy;
        const done = () => { const t = btn.textContent; btn.textContent = 'コピーしました'; setTimeout(() => btn.textContent = t, 1600); };
        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(text).then(done, () => fallback());
        } else {
            fallback();
        }
        function fallback() {
            const ta = document.createElement('textarea');
            ta.value = text; ta.style.position = 'fixed'; ta.style.opacity = '0';
            document.body.appendChild(ta); ta.select();
            try { document.execCommand('copy'); done(); } catch (e) { prompt('この文章をコピーしてください', text); }
            ta.remove();
        }
    }
</script>
<script>
    // 入力途中のフォームをこのブラウザに一時保存し、ページを移動して戻っても消えないようにする
    // (data-draft="キー" をつけたフォームが対象。送信したら消える)
    document.querySelectorAll('form[data-draft]').forEach((form) => {
        const key = 'draft:' + form.dataset.draft;
        const skip = ['_token', '_method', 'password', 'password_confirmation'];
        let saved = null;
        try { saved = JSON.parse(localStorage.getItem(key) || 'null'); } catch (e) {}

        if (saved && ! @json($errors->any())) {
            const values = (name) => [].concat(saved[name] ?? []).map(String);
            [...form.elements].forEach((el) => {
                if (! el.name || skip.includes(el.name) || el.type === 'file') return;
                if (el.type === 'checkbox' || el.type === 'radio') {
                    el.checked = values(el.name).includes(el.value);
                } else if (el.name in saved) {
                    el.value = values(el.name)[0] ?? '';
                }
                el.dispatchEvent(new Event('change', { bubbles: true }));
            });
            const note = document.createElement('div');
            note.className = 'mb-4 rounded-lg bg-amber-50 border border-amber-200 text-amber-800 px-4 py-2 text-sm flex justify-between gap-3';
            note.innerHTML = '<span>前回の入力途中の内容を復元しました。</span>';
            const clear = document.createElement('button');
            clear.type = 'button';
            clear.className = 'underline whitespace-nowrap';
            clear.textContent = '破棄する';
            clear.onclick = () => { try { localStorage.removeItem(key); } catch (e) {} location.reload(); };
            note.appendChild(clear);
            form.prepend(note);
        }

        const saveDraft = () => {
            const data = {};
            new FormData(form).forEach((value, name) => {
                if (skip.includes(name) || value instanceof File) return;
                data[name] = name in data ? [].concat(data[name], value) : value; // チェックボックスは複数の値を持てる
            });
            try { localStorage.setItem(key, JSON.stringify(data)); } catch (e) {}
        };
        form.addEventListener('input', saveDraft);
        form.addEventListener('change', saveDraft);
        form.addEventListener('submit', () => { try { localStorage.removeItem(key); } catch (e) {} });
        // 「キャンセル」(data-discard)を押したら、覚えていた入力途中の内容も捨てる
        form.querySelectorAll('[data-discard]').forEach((el) => el.addEventListener('click', () => {
            form.removeEventListener('input', saveDraft);
            form.removeEventListener('change', saveDraft);
            try { localStorage.removeItem(key); } catch (e) {}
        }));
    });
</script>

@if (! empty($tourSteps))
{{-- 使い方ガイド:文章は config/tour.php で編集できます --}}
<div id="tour-spot" class="hidden fixed z-40 rounded-xl pointer-events-none transition-all duration-200"
     style="box-shadow: 0 0 0 9999px rgba(15, 23, 42, .45), 0 0 0 3px #c0521d;"></div>
<div id="tour-pop" role="dialog" aria-modal="false" aria-labelledby="tour-title"
     class="hidden fixed z-50 w-[min(340px,calc(100vw-32px))] bg-white rounded-xl shadow-xl border border-stone-200 p-4">
    <div id="tour-arrow" class="absolute w-3 h-3 bg-white border-stone-200 rotate-45"></div>
    <p class="text-xs text-brand-600 font-bold mb-1" id="tour-count"></p>
    <h3 id="tour-title" class="font-bold mb-1"></h3>
    <p id="tour-text" class="text-sm text-stone-600 leading-relaxed"></p>
    <div class="flex items-center justify-between mt-4">
        <button type="button" id="tour-skip" class="text-xs text-stone-500 hover:text-ink underline">スキップ</button>
        <div class="flex gap-2">
            <button type="button" id="tour-prev" class="rounded-lg border border-stone-300 bg-white px-3 py-1.5 text-sm">戻る</button>
            <button type="button" id="tour-next" class="rounded-lg bg-brand-500 hover:bg-brand-600 text-white font-bold px-3 py-1.5 text-sm">次へ</button>
        </div>
    </div>
</div>
<script>
    (() => {
        const allSteps = @json($tourSteps);
        const seenKey = 'tour-seen:v2:' + @json($tourKey); // 内容を変えたら v の数字を上げると、もう一度自動で出る
        const spot = document.getElementById('tour-spot');
        const pop = document.getElementById('tour-pop');
        const arrow = document.getElementById('tour-arrow');
        let steps = [], i = 0;

        const targetOf = (s) => document.querySelector(`[data-tour="${s.target}"]`);

        function place() {
            const el = targetOf(steps[i]);
            const r = el.getBoundingClientRect();
            const pad = 6;
            Object.assign(spot.style, {
                top: (r.top - pad) + 'px', left: (r.left - pad) + 'px',
                width: (r.width + pad * 2) + 'px', height: (r.height + pad * 2) + 'px',
            });

            const pw = pop.offsetWidth, ph = pop.offsetHeight, gap = 14, vw = innerWidth, vh = innerHeight;
            const below = r.bottom + gap + ph < vh || r.top - gap - ph < 0;
            const top = below ? r.bottom + pad + gap : r.top - pad - gap - ph;
            const left = Math.min(Math.max(16, r.left + r.width / 2 - pw / 2), vw - pw - 16);
            pop.style.top = Math.max(16, Math.min(top, vh - ph - 16)) + 'px';
            pop.style.left = left + 'px';

            const ax = Math.min(Math.max(16, r.left + r.width / 2 - left - 6), pw - 28);
            arrow.style.left = ax + 'px';
            arrow.style.top = below ? '-7px' : '';
            arrow.style.bottom = below ? '' : '-7px';
            arrow.className = 'absolute w-3 h-3 bg-white border-stone-200 rotate-45 ' + (below ? 'border-l border-t' : 'border-r border-b');
        }

        function show() {
            const s = steps[i];
            document.getElementById('tour-count').textContent = `使い方 ${i + 1} / ${steps.length}`;
            document.getElementById('tour-title').textContent = s.title;
            document.getElementById('tour-text').textContent = s.text;
            document.getElementById('tour-prev').classList.toggle('invisible', i === 0);
            document.getElementById('tour-next').textContent = i === steps.length - 1 ? 'はじめる' : '次へ';
            spot.classList.remove('hidden');
            pop.classList.remove('hidden');
            targetOf(s).scrollIntoView({ block: 'center', behavior: 'smooth' });
            place();
            setTimeout(place, 350); // スクロールが終わってから位置を合わせ直す
            document.getElementById('tour-next').focus({ preventScroll: true });
        }

        function end() {
            spot.classList.add('hidden');
            pop.classList.add('hidden');
            try { localStorage.setItem(seenKey, '1'); } catch (e) {}
        }

        window.startTour = (force = false) => {
            let seen = false;
            try { seen = localStorage.getItem(seenKey) === '1'; } catch (e) {}
            if (seen && ! force) return;
            steps = allSteps.filter((s) => targetOf(s)); // 画面にない場所は飛ばす
            if (! steps.length) return;
            i = 0;
            show();
        };

        document.getElementById('tour-next').onclick = () => (i < steps.length - 1 ? (i++, show()) : end());
        document.getElementById('tour-prev').onclick = () => { if (i > 0) { i--; show(); } };
        document.getElementById('tour-skip').onclick = end;
        document.addEventListener('keydown', (e) => {
            if (pop.classList.contains('hidden')) return;
            if (e.key === 'Escape') end();
            if (e.key === 'ArrowRight') document.getElementById('tour-next').click();
            if (e.key === 'ArrowLeft') document.getElementById('tour-prev').click();
        });
        addEventListener('resize', () => { if (! pop.classList.contains('hidden')) place(); });
        addEventListener('scroll', () => { if (! pop.classList.contains('hidden')) place(); }, { passive: true });

        // 自動で始めるのはホームを初めて開いたときだけ
        // (公開デモ版では、機能を知ってもらうため各ページの初回にも出す)
        if (@json($tourKey) === 'dashboard' || @json((bool) config('demo.enabled'))) {
            addEventListener('load', () => setTimeout(() => startTour(false), 300));
        }
    })();
</script>
@endif
</body>
</html>
