@props(['name', 'label', 'type' => 'text', 'value' => null, 'required' => false, 'maxMo' => 5])
<div {{ $attributes->only('class') }}>
    <label for="{{ $name }}" class="label">{{ $label }}@if ($required || $type === 'file') <span class="text-red-500" data-req-star @unless ($required) hidden @endunless>*</span>@endif</label>
    @if ($type === 'textarea')
        <textarea id="{{ $name }}" name="{{ $name }}" rows="2" class="input" @required($required)>{{ old($name, $value) }}</textarea>
    @elseif ($type === 'select')
        <select id="{{ $name }}" name="{{ $name }}" class="input" @required($required)>{{ $slot }}</select>
    @elseif ($type === 'file')
        <input id="{{ $name }}" name="{{ $name }}" type="file" class="input file-input" data-max="{{ $maxMo * 1048576 }}" @required($required) {{ $attributes->except('class') }}>
        <p class="mt-1 text-xs text-slate-500" data-file-hint>{{ collect(explode(',', (string) $attributes->get('accept')))->map(fn ($e) => strtoupper(ltrim(trim($e), '.')))->filter()->implode(', ') }} · {{ $maxMo }} Mo max</p>
    @else
        <input id="{{ $name }}" name="{{ $name }}" type="{{ $type }}" class="input" @required($required)
               @if ($type !== 'password') value="{{ old($name, $value) }}" @endif
               {{ $attributes->except('class') }}>
    @endif
    @error($name)<p class="field-error">{{ $message }}</p>@enderror
</div>
