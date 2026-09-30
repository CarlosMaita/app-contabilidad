@props(['groups', 'mobile' => false])

@foreach ($groups as $label => $items)
    <div class="border-b border-neutral-300 {{ $mobile ? 'py-2' : 'py-3' }}">
        <div class="kicker px-4 pb-1.5 {{ $mobile ? 'pt-1' : '' }}">{{ $label }}</div>
        @foreach ($items as $item)
            @php($active = request()->routeIs($item['active']))
            <a href="{{ route($item['route']) }}" wire:navigate
               @if ($active) aria-current="page" @endif
               class="block border-l-[3px] pl-[13px] pr-3 {{ $mobile ? 'py-3 text-[15px]' : 'py-[7px] text-[13px]' }} {{ $active ? 'border-accent bg-neutral-200 font-extrabold' : 'border-transparent hover:bg-neutral-200' }}">
                {{ $item['label'] }}
            </a>
        @endforeach
    </div>
@endforeach
