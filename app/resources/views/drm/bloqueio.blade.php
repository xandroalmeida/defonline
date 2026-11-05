{{-- STORY-084 — bloqueio do Raio X do Negócio sem plano/trial (spec STORY-070 §4, variante). --}}
<x-layouts.app title="Raio X do Negócio">
    <x-bloqueio-sem-saldo :titulo="$titulo" :causa="$causa" :rascunho="$rascunho" prefixo="screen-bloqueio-raiox"
                          :acoes="\App\Support\Cobranca\TextoBloqueio::disponiveis($acoes)" :rodape="false"/>

    @if ($empresa !== null && $ultimoMes !== null)
        <p class="m-0 mt-4 text-sm text-[color:var(--color-secondary)]">
            Os meses já lançados continuam visíveis.
            <x-link :href="route('drm.mes', [$empresa, $ultimoMes])" dusk="screen-bloqueio-raiox-ver-meses">Ver o último mês lançado</x-link>
        </p>
    @endif
</x-layouts.app>
