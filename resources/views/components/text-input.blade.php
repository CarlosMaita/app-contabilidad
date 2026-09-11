@props(['disabled' => false])

<input @disabled($disabled) {{ $attributes->merge(['class' => 'wf-input']) }}>
