@props(['size' => 'w-7 h-7'])

{{-- シフトノートのマーク(カレンダーにチェック) --}}
<span {{ $attributes->merge(['class' => "$size shrink-0 inline-flex rounded-lg bg-brand-500 text-white items-center justify-center"]) }} aria-hidden="true">
    <svg viewBox="0 0 24 24" class="w-[62%] h-[62%]" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
        <rect x="3.5" y="5" width="17" height="15" rx="2.5"/><path d="M3.5 9.5h17M8 3v4M16 3v4"/><path d="M8.5 14.5l2.3 2.3 4.7-4.6"/>
    </svg>
</span>
