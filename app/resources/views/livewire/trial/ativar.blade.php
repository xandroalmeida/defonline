{{-- STORY-074 / spec STORY-070-trial. Dias e análises vêm da configuração; nada é literal. --}}
@php
    use App\Support\Cobranca\TextoTrial;
@endphp

<div class="max-w-[640px]" dusk="screen-trial">
    @if ($recemAtivado && $trial)
        <section class="flex flex-col gap-4" dusk="screen-trial-confirmacao"
                 x-data x-init="$nextTick(() => $refs.titulo.focus())">
            <h1 x-ref="titulo" tabindex="-1" class="text-[length:var(--text-h1)] font-medium leading-tight tracking-tight text-[color:var(--color-primary)] m-0">Seu teste começou</h1>
            <p class="m-0 text-[color:var(--color-primary)]">
                Você tem até {{ \App\Support\DataExibicao::data($trial->proximo_vencimento) }} para fazer {{ TextoTrial::analises($trial->cota_snapshot) }} e lançar no Raio X do Negócio.
            </p>
            <div class="flex flex-wrap gap-3">
                @if ($temEmpresa)
                    <x-button :href="route('diagnosticos.selecionar')" variant="primary" dusk="screen-trial-fazer-analise">Fazer uma análise</x-button>
                    <x-button :href="route('empresas.nova')" variant="secondary" dusk="screen-trial-cadastrar-empresa">Cadastrar uma empresa</x-button>
                @else
                    <x-button :href="route('empresas.nova')" variant="primary" dusk="screen-trial-cadastrar-empresa">Cadastrar uma empresa</x-button>
                @endif
            </div>
        </section>
    @elseif ($trial)
        <x-page-header :title="'Teste grátis'"/>
        @if ($aviso)
            <x-drm.aviso-inline :variant="$aviso['severidade']" dusk="screen-trial-aviso">{{ $aviso['texto'] }}</x-drm.aviso-inline>
        @endif
        <x-button :href="$urlPlanos" variant="secondary" dusk="screen-trial-ver-planos">Ver planos</x-button>
    @elseif ($erroInesperado)
        <x-page-header :title="'Teste grátis'"/>
        <x-drm.aviso-inline variant="bloqueio" dusk="screen-trial-error">
            <p class="m-0 mb-2">Não conseguimos ativar o teste agora. Tente de novo em instantes.</p>
            <x-button type="button" variant="secondary" size="sm" wire:click="ativar" dusk="screen-trial-tentar-de-novo">Tentar de novo</x-button>
        </x-drm.aviso-inline>
    @elseif ($inelegivel)
        <x-page-header :title="'Teste grátis'"/>
        <x-drm.aviso-inline variant="bloqueio" dusk="{{ $inelegivel->value === 'desativado' ? 'screen-trial-indisponivel' : 'screen-trial-error-inelegivel' }}">
            {{ TextoTrial::inelegivel($inelegivel) }}
        </x-drm.aviso-inline>
        <x-button :href="$urlPlanos" variant="primary" dusk="screen-trial-ver-planos">{{ $inelegivel->value === 'desativado' ? 'Ver planos' : 'Ver planos e preços' }}</x-button>
    @elseif ($oferta)
        <x-page-header :title="'Teste grátis'"/>
        @if ($personificando)
            <x-drm.aviso-inline variant="info">Você está logado como administrador: o botão abaixo fica desativado.</x-drm.aviso-inline>
        @endif
        <x-card as="section" dusk="screen-trial-oferta">
            <h2 class="text-[length:var(--text-h3)] font-medium text-[color:var(--color-primary)] m-0 mb-1">Teste grátis</h2>
            <p class="m-0 mb-1 text-[color:var(--color-primary)]">{{ $oferta->dias }} {{ $oferta->dias === 1 ? 'dia' : 'dias' }}, {{ TextoTrial::analises($oferta->analises) }} e o Raio X do Negócio.</p>
            <p class="m-0 mb-4 text-[color:var(--color-secondary)]">Sem cartão e sem cobrança no fim.</p>
            <x-button type="button" variant="primary" wire:click="ativar" wire:loading.attr="disabled" :disabled="$personificando" dusk="screen-trial-ativar">
                <span wire:loading.remove wire:target="ativar">Começar teste grátis</span>
                <span wire:loading wire:target="ativar">Ativando…</span>
            </x-button>
        </x-card>
    @endif
</div>
