{{--
    Um grupo do Anexo K: aviso de herança parcial + seletor de modo (DDR-002) + campos.
    Vars: $grupo (App\Domain\Drm\Grupo), $setor, $modos, $conteudo, $repetidos, $origem, $total_anterior.
--}}
@php
    $modo = $modos[$grupo->value] ?? 'detalhado';
    $campos = $modo === 'agregado' ? [$grupo->totalizador()] : $grupo->itens($setor);
    $duasColunas = count($campos) > 8;
    $soma = array_key_exists($grupo->value, $somados);
@endphp

@if ($modo === 'detalhado' && isset($total_anterior[$grupo->value]) && $origem)
    <x-drm.aviso-inline variant="info" dusk="drm-lancamento-heranca-parcial-{{ $grupo->value }}">
        Em {{ $origem->mesPorExtenso() }} você informou este grupo pelo total, então os itens começam vazios.
        O total de {{ $origem->mesPorExtenso() }} era {{ \App\Domain\Drm\Dinheiro::brl($total_anterior[$grupo->value]) }}.
    </x-drm.aviso-inline>
@endif

@if ($confirmando_detalhar === $grupo->value)
    <x-drm.aviso-inline variant="alerta" titulo="Detalhar os itens deste grupo?" dusk="drm-lancamento-modo-{{ $grupo->value }}-confirmar">
        <p class="m-0 mb-3">O total de {{ \App\Domain\Drm\Dinheiro::brl((int) \App\Domain\Drm\Dinheiro::interpretar($valores[$grupo->totalizador()] ?? null)[1]) }} que você informou será descartado, e os {{ count($grupo->itens($setor)) }} itens começam vazios.</p>
        <div class="flex flex-wrap gap-3">
            <x-button type="button" variant="primary" wire:click="confirmarDetalhar"
                      x-init="$nextTick(() => $el.focus())"
                      x-on:keydown.escape.window="$wire.cancelarDetalhar()"
                      dusk="drm-lancamento-modo-{{ $grupo->value }}-confirmar-sim">Detalhar por item</x-button>
            <x-button type="button" variant="secondary" wire:click="cancelarDetalhar"
                      dusk="drm-lancamento-modo-{{ $grupo->value }}-confirmar-nao">Cancelar</x-button>
        </div>
    </x-drm.aviso-inline>
@endif

<x-drm.seletor-modo :grupo="$grupo->value" :modo="$modo"
                    :campos-detalhado="$grupo->custoEmCampos($setor, \App\Domain\Drm\Modo::Detalhado)"/>

<div class="grid grid-cols-1 gap-4 {{ $duasColunas ? 'lg:grid-cols-2' : '' }} border-t border-[color:var(--color-border)] pt-4">
    @foreach ($campos as $campo)
        <x-drm.campo-monetario :campo="$campo"
                               :rotulo="(string) $conteudo->rotuloCampo($campo)"
                               :help="$conteudo->help($campo)"
                               :herdado="in_array($campo, $repetidos, true) && $origem ? 'veio de '.$origem->mesPorExtenso() : null"
                               :nota="$soma && $campo === $grupo->totalizador() ? 'somamos os itens que você já tinha preenchido — pode ajustar' : null"/>
    @endforeach
</div>
