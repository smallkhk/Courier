@props(['name', 'label', 'type' => 'text', 'value' => null, 'hint' => null, 'required' => false, 'options' => null, 'placeholder' => null, 'rows' => 3, 'errorKey' => null])
@php
    $id = 'f_'.str_replace(['[', ']', '.'], '_', $name);
    $key = $errorKey ?? str_replace(['[', ']'], ['.', ''], $name);
    $hasError = $errors->has($key);
    $val = old($key, $value);
    $describedBy = trim(($hint ? $id.'_hint ' : '').($hasError ? $id.'_error' : ''));
@endphp
<div {{ $attributes->only('class') }}>
    <label for="{{ $id }}" class="label">{{ $label }}@if($required)<span class="text-danger-700" aria-hidden="true"> *</span>@endif</label>
    @if($type === 'select')
        <select id="{{ $id }}" name="{{ $name }}" class="input" @if($required) required @endif aria-invalid="{{ $hasError ? 'true' : 'false' }}" @if($describedBy) aria-describedby="{{ $describedBy }}" @endif {{ $attributes->except(['class']) }}>
            @if($placeholder !== false)<option value="">{{ $placeholder ?? 'Select…' }}</option>@endif
            @foreach($options ?? [] as $k => $v)
                <option value="{{ $k }}" @selected((string) $val === (string) $k)>{{ $v }}</option>
            @endforeach
        </select>
    @elseif($type === 'textarea')
        <textarea id="{{ $id }}" name="{{ $name }}" rows="{{ $rows }}" class="input" placeholder="{{ $placeholder }}" @if($required) required @endif aria-invalid="{{ $hasError ? 'true' : 'false' }}" @if($describedBy) aria-describedby="{{ $describedBy }}" @endif {{ $attributes->except(['class']) }}>{{ $val }}</textarea>
    @else
        <input id="{{ $id }}" type="{{ $type }}" name="{{ $name }}" value="{{ $type === 'password' ? '' : $val }}" class="input" placeholder="{{ $placeholder }}" @if($required) required @endif aria-invalid="{{ $hasError ? 'true' : 'false' }}" @if($describedBy) aria-describedby="{{ $describedBy }}" @endif {{ $attributes->except(['class']) }}>
    @endif
    @if($hint)<p id="{{ $id }}_hint" class="hint">{{ $hint }}</p>@endif
    @error($key)<p id="{{ $id }}_error" class="error-text"><x-icon name="alert" class="size-4" />{{ $message }}</p>@enderror
</div>
