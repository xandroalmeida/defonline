{{-- Item do menu lateral (app-nav): link, ou span desabilitado com tooltip. `icone` é o SVG interno, confiável (vem do próprio app-nav). --}}
@props(['item', 'icone'])

@if ($item['disabled'])
    <span class="nav-item is-disabled"
          aria-disabled="true"
          tabindex="-1"
          title="{{ $item['tooltip'] }}"
          data-tooltip="{{ $item['tooltip'] }}"
          dusk="{{ $item['dusk'] }}">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor"
             stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            {!! $icone !!}
        </svg>
        <span>{{ $item['label'] }}</span>
    </span>
@else
    <a href="{{ $item['href'] }}"
       class="nav-item @if ($item['active']) is-active @endif"
       @if ($item['active']) aria-current="page" @endif
       dusk="{{ $item['dusk'] }}"
       @click="navOpen = false">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor"
             stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            {!! $icone !!}
        </svg>
        <span>{{ $item['label'] }}</span>
    </a>
@endif
