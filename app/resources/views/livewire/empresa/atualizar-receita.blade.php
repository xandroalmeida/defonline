{{-- STORY-064: ação "Atualizar dados da Receita". CPF não renderiza nada (CA-1). --}}
<div class="flex flex-col gap-3" dusk="atualizar-receita">
@if ($cnpj)
    @if ($legada)
        <p class="text-sm rounded-md border border-[color:var(--color-farol-amarelo)] bg-[color:var(--color-neutral)] text-[color:var(--color-primary)] px-3 py-2 m-0"
           role="status" dusk="empresa-aviso-legada">
            Estes dados não foram confirmados na Receita. Atualize para ver a ficha completa.
        </p>
    @endif

    <div class="flex flex-wrap items-center gap-3">
        @if ($consultadoEm)
            <span class="text-sm text-[color:var(--color-secondary)]" dusk="empresa-show-consultado-em">
                Dados da Receita de {{ $consultadoEm }}
            </span>
        @endif
        <x-button type="button" variant="secondary" size="sm" wire:click="atualizar"
                  wire:loading.attr="disabled" wire:target="atualizar" dusk="empresa-atualizar-receita">
            <span wire:loading.remove wire:target="atualizar">Atualizar dados da Receita</span>
            <span wire:loading wire:target="atualizar">Consultando a Receita…</span>
        </x-button>
    </div>

    @if ($erro)
        <p class="text-sm text-[color:var(--color-destructive)] m-0" role="alert" dusk="empresa-atualizar-erro">{{ $erro }}</p>
    @elseif ($resultado === 'atualizada')
        <p class="text-sm text-[color:var(--color-secondary)] m-0" role="status" dusk="empresa-atualizar-ok">Dados da Receita atualizados.</p>
    @elseif ($resultado === 'ja_atualizada')
        <p class="text-sm text-[color:var(--color-secondary)] m-0" role="status" dusk="empresa-atualizar-ja">Os dados da Receita já estão atualizados.</p>
    @endif
@endif
</div>
