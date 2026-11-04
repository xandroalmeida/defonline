@props(['ativa' => false, 'marcada' => false, 'marcaRotulo' => null])

<button type="button" role="tab"
        aria-selected="{{ $ativa ? 'true' : 'false' }}"
        tabindex="{{ $ativa ? '0' : '-1' }}"
        {{ $attributes->merge(['class' => 'tab']) }}>
    {{ $slot }}
    @if ($marcada)
        <span class="tab__marca" role="img" aria-label="{{ $marcaRotulo }}"></span>
    @endif
</button>
