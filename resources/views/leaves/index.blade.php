@extends('layouts.app', ['title' => '有給'])

@use('App\Models\Shift')

@section('content')
<h1 class="text-2xl font-bold mb-1">有給</h1>
<p class="text-sm text-stone-600 mb-5">スタッフが自分のページから申請します。承認すると残り日数が1日減り、その日のシフトから外れます。</p>

<div class="grid lg:grid-cols-3 gap-5">
    <div class="lg:col-span-2 space-y-5">
        <div class="flex gap-1 border-b border-stone-200 text-sm">
            <a href="{{ route('leaves.index') }}" class="px-3 py-2 border-b-2 {{ $tab === 'pending' ? 'border-brand-500 text-brand-700 font-bold' : 'border-transparent text-stone-500' }}">承認待ち({{ $pending->count() }})</a>
            <a href="{{ route('leaves.index', ['tab' => 'all']) }}" class="px-3 py-2 border-b-2 {{ $tab === 'all' ? 'border-brand-500 text-brand-700 font-bold' : 'border-transparent text-stone-500' }}">これまでの申請</a>
        </div>

        @if ($tab === 'pending')
            <section data-tour="leave-pending" class="space-y-3">
                @forelse ($pending as $leave)
                    <article class="bg-white rounded-xl border border-stone-200 p-4">
                        <div class="flex flex-wrap items-baseline justify-between gap-2">
                            <p class="font-bold">{{ $leave->staff->name }} <span class="ml-1">{{ Shift::label($leave->day) }}</span></p>
                            <p class="text-xs text-stone-500">{{ $leave->created_at->format('n/j H:i') }}に申請 ・ 残り{{ $leave->staff->paid_leave_balance }}日</p>
                        </div>
                        @if ($leave->note)<p class="mt-1 text-sm text-stone-700">「{{ $leave->note }}」</p>@endif
                        <form method="POST" class="mt-3 flex flex-wrap items-center gap-2">
                            @csrf
                            <input type="text" name="reply" maxlength="200" placeholder="ひとこと(任意。却下の理由など)" onkeydown="if (event.key === 'Enter') event.preventDefault()" class="flex-1 min-w-48 rounded-lg border border-stone-300 px-3 py-1.5 text-sm">
                            <button formaction="{{ route('leaves.approve', $leave) }}" class="rounded-lg bg-brand-500 hover:bg-brand-600 text-white font-bold px-4 py-1.5 text-sm">承認する</button>
                            <button formaction="{{ route('leaves.reject', $leave) }}" class="rounded-lg border border-stone-300 bg-white hover:bg-stone-50 px-4 py-1.5 text-sm">却下する</button>
                        </form>
                    </article>
                @empty
                    <div class="bg-white rounded-xl border border-stone-200">
                        <x-empty-state message="承認待ちの申請はありません。" />
                    </div>
                @endforelse
            </section>
        @else
            <div class="bg-white rounded-xl border border-stone-200 overflow-hidden">
                @if ($history->isEmpty())
                    <x-empty-state message="まだ申請はありません。" />
                @else
                    <table class="rtable w-full text-sm">
                        <thead class="bg-stone-50 text-stone-500 text-left">
                            <tr>
                                <th class="px-4 py-3 font-medium">日付</th>
                                <th class="px-4 py-3 font-medium">スタッフ</th>
                                <th class="px-4 py-3 font-medium">結果</th>
                                <th class="px-4 py-3"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-stone-100">
                            @foreach ($history as $leave)
                                <tr>
                                    <td class="rt-main px-4 py-3 font-bold whitespace-nowrap">{{ Shift::label($leave->day) }}</td>
                                    <td data-label="スタッフ" class="px-4 py-3">{{ $leave->staff->name }}</td>
                                    <td data-label="結果" class="px-4 py-3">
                                        <div>
                                            <span class="{{ $leave->status === 'approved' ? 'text-brand-700 font-bold' : 'text-stone-500' }}">{{ $leave->status_label }}</span>
                                            @if ($leave->reply)<span class="block text-xs text-stone-500">「{{ $leave->reply }}」</span>@endif
                                        </div>
                                    </td>
                                    <td class="rt-actions px-4 py-3 text-right">
                                        @if ($leave->status === 'approved')
                                            <form method="POST" action="{{ route('leaves.revoke', $leave) }}" onsubmit="return confirm('承認を取り消して、有給の残りを1日戻します。よろしいですか?')">
                                                @csrf
                                                <button class="rounded-lg border border-stone-300 bg-white hover:bg-stone-50 px-3 py-1.5">承認を取り消す</button>
                                            </form>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
        @endif
    </div>

    <aside data-tour="leave-balance" class="bg-white rounded-xl border border-stone-200 p-5 self-start">
        <h2 class="font-bold">有給の残り日数</h2>
        @if ($balances->isEmpty())
            <p class="mt-2 text-sm text-stone-500">スタッフがいません。</p>
        @else
            <ul class="mt-3 divide-y divide-stone-100 text-sm">
                @foreach ($balances as $m)
                    <li class="py-2 flex items-center justify-between gap-2">
                        <a href="{{ route('staff.edit', $m) }}" class="hover:underline">{{ $m->name }}</a>
                        <span class="{{ $m->paid_leave_balance ? 'font-bold' : 'text-stone-400' }}">{{ $m->paid_leave_balance }}日</span>
                    </li>
                @endforeach
            </ul>
        @endif
        <p class="mt-3 text-xs text-stone-500">付与されたときや期限で消えたときは、名前を押して「スタッフの編集」で日数を書き換えてください。</p>
    </aside>
</div>
@endsection
