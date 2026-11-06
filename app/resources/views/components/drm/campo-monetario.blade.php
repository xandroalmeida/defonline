{{--
    <x-drm.campo-monetario> — `.input-affix` + dica + estado `herdado` (DDR-003).
    Props:
      campo    D01..D42 (compõe id, dusk e o caminho `valores.<campo>` do wire:model)
      rotulo   texto do <label>
      help     tooltip; sem texto, não há <x-help> (PDR-009 §1)
      herdado  texto da marca ("veio de julho") ou null; liga ao campo por aria-describedby
      nota     legenda de baixo que não é herança (ex.: "somamos os itens...")
--}}
@props(['campo', 'rotulo', 'help' => null, 'herdado' => null, 'nota' => null])
@php
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
    <x-input-moeda :id="'campo-'.$campo" :name="$campo"
                   wire:model.live.debounce.300ms="valores.{{ $campo }}"
                   :aria-describedby="$descrito !== '' ? $descrito : null"
                   :aria-invalid="$errors->has('valores.'.$campo) ? 'true' : null"
                   dusk="drm-lancamento-campo-{{ $campo }}"/>
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
