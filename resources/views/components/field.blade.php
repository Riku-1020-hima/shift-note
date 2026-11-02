@props(['label', 'name', 'hint' => null])

<label class="block">
    <span class="block text-sm font-medium mb-1">{{ $label }}</span>
    {{ $slot }}
    @if ($hint)
        <span class="block text-xs text-stone-500 mt-1">{{ $hint }}</span>
    @endif
    @error($name)
        <span class="block text-xs text-alert-600 mt-1">{{ $message }}</span>
    @enderror
</label>
