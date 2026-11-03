@props(['name', 'label', 'type' => 'text', 'value' => null, 'required' => false])
<div {{ $attributes->only('class') }}>
    <label for="{{ $name }}" class="label">{{ $label }}@if ($required) <span class="text-red-500">*</span>@endif</label>
    @if ($type === 'textarea')
        <textarea id="{{ $name }}" name="{{ $name }}" rows="2" class="input" @required($required)>{{ old($name, $value) }}</textarea>
    @elseif ($type === 'select')
        <select id="{{ $name }}" name="{{ $name }}" class="input" @required($required)>{{ $slot }}</select>
    @else
        <input id="{{ $name }}" name="{{ $name }}" type="{{ $type }}" class="input" @required($required)
               @if ($type !== 'password' && $type !== 'file') value="{{ old($name, $value) }}" @endif
               {{ $attributes->except('class') }}>
    @endif
    @error($name)<p class="field-error">{{ $message }}</p>@enderror
</div>
