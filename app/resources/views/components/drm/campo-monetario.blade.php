{{--
    <x-drm.campo-monetario> — `.input-affix` + dica + estado `herdado` (DDR-003).
    Props:
      campo    D01..D42 (compõe id, dusk e o caminho `valores.<campo>` do wire:model)
      rotulo   texto do <label>
      help     tooltip; sem texto, não há <x-help> (PDR-009 §1)
      herdado  texto da marca ("veio de julho") ou null; liga ao campo por aria-describedby
      nota     legenda de baixo que não é herança (ex.: "somamos os itens...")
    Máscara BRL: reemite `input` quando reescreve o valor (BUG-001), senão o Livewire guarda o
    texto de antes da formatação.
--}}
@props(['campo', 'rotulo', 'help' => null, 'herdado' => null, 'nota' => null])
@php
    $commit = "if (\$el.value !== v) { \$el.value = v; \$el.dispatchEvent(new Event('input')); }";
    $mascara = <<<'JS'
        let v = '';
        const raw = $el.value.replace(/\D/g, '');
        if (raw !== '') {
            const cents = raw.padStart(3, '0');
            const inteiro = cents.slice(0, -2).replace(/\B(?=(\d{3})+(?!\d))/g, '.').replace(/^0+(?=\d)/, '') || '0';
            v = inteiro + ',' + cents.slice(-2);
        }
    JS."\n".$commit;
    $descrito = collect([
        $herdado ? "herdado-{$campo}" : null,
        $nota ? "nota-{$campo}" : null,
        $errors->has("valores.{$campo}") ? "erro-{$campo}" : null,
    ])->filter()->implode(' ');
@endphp
<div>
    <div class="flex items-center gap-1.5 mb-1">
        <x-label :for="'campo-'.$campo" class="mb-0">{{ $rotulo }}</x-label>
        <x-help :id="$campo" :text="$help" :label="$rotulo"/>
    </div>
    <label class="input-affix" for="campo-{{ $campo }}">
        <span class="input-affix__symbol" aria-hidden="true">R$</span>
        <input type="text" id="campo-{{ $campo }}" name="{{ $campo }}"
               class="input-affix__input"
               wire:model.live.debounce.300ms="valores.{{ $campo }}"
               inputmode="decimal" autocomplete="off" placeholder="0,00"
               @if ($descrito !== '') aria-describedby="{{ $descrito }}" @endif
               @if ($errors->has("valores.{$campo}")) aria-invalid="true" @endif
               x-data x-on:input="{!! $mascara !!}"
               dusk="drm-lancamento-campo-{{ $campo }}">
    </label>
    @if ($herdado)
        <p id="herdado-{{ $campo }}" class="text-xs text-[color:var(--color-secondary)] mt-1 mb-0" dusk="drm-lancamento-herdado-{{ $campo }}">{{ $herdado }}</p>
    @endif
    @if ($nota)
        <p id="nota-{{ $campo }}" class="text-xs text-[color:var(--color-secondary)] mt-1 mb-0">{{ $nota }}</p>
    @endif
    @error("valores.{$campo}")
        <p id="erro-{{ $campo }}" class="text-[color:var(--color-destructive)] text-sm mt-1 mb-0" dusk="drm-lancamento-erro-{{ $campo }}">{{ $message }}</p>
    @enderror
</div>
