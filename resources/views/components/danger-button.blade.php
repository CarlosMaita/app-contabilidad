<button {{ $attributes->merge(['type' => 'submit', 'class' => 'btn bg-accent-700 text-white hover:bg-accent-800 active:bg-accent-900']) }}>
    {{ $slot }}
</button>
