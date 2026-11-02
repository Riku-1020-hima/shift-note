@props(['message', 'action' => null, 'url' => null])

{{-- データが空のときに「次にやること」を見せる --}}
<div class="py-8 text-center">
    <p class="text-sm text-stone-500 mb-3">{{ $message }}</p>
    @if ($action && $url)
        <a href="{{ $url }}" class="inline-block rounded-lg bg-brand-500 hover:bg-brand-600 text-white text-sm font-bold px-4 py-2">{{ $action }} →</a>
    @endif
    {{ $slot }}
</div>
