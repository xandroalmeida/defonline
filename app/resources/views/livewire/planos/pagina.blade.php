{{-- STORY-075 / spec STORY-070-planos-e-precos. Tudo vem do catálogo; nenhum nome, preço ou cota é literal. --}}
@php
    use App\Domain\Cobranca\VitrinePrecos;
    use App\Domain\Drm\Dinheiro;

    $brl = fn (int $centavos): string => Dinheiro::brl($centavos);
    $analises = fn (int $n): string => $n.' '.($n === 1 ? 'análise' : 'análises');
    $semNada = ! $erro && $planos->isEmpty() && $pacotes->isEmpty();
@endphp

<div class="w-full max-w-[960px] mx-auto" dusk="screen-planos">
    <x-page-header :title="'Planos e preços'" :subtitle="'Escolha como quer usar o DEFOnline.'"/>

    @if ($erro)
        <x-drm.aviso-inline variant="bloqueio" dusk="screen-planos-erro">
            <p class="m-0 mb-2">Não conseguimos carregar os planos agora.</p>
            <x-button type="button" variant="secondary" size="sm" wire:click="$refresh" dusk="screen-planos-tentar-de-novo">Tentar de novo</x-button>
        </x-drm.aviso-inline>
    @elseif ($semNada)
        <x-drm.aviso-inline variant="info" dusk="screen-planos-vazio">
            <p class="m-0 mb-2">Voltamos em breve com novas ofertas.</p>
            <x-link :href="$logado ? route('home') : route('landing')">Voltar</x-link>
        </x-drm.aviso-inline>
    @else
        @if ($personificando)
            <x-drm.aviso-inline variant="info" dusk="screen-planos-personificando">
                Você está logado como administrador: os botões abaixo ficam desativados.
            </x-drm.aviso-inline>
        @endif

        @if ($trial)
            <x-card as="section" class="mb-6" aria-labelledby="planos-teste-titulo" dusk="screen-planos-teste">
                <h2 id="planos-teste-titulo" class="text-[length:var(--text-h3)] font-medium text-[color:var(--color-primary)] m-0 mb-1">Teste grátis</h2>
                <p class="m-0 mb-3 text-[color:var(--color-secondary)]">{{ $trial->dias }} {{ $trial->dias === 1 ? 'dia' : 'dias' }}, {{ $analises($trial->analises) }} e o Raio X do Negócio. Sem cartão.</p>
                @if (! $logado)
                    <x-button :href="$urlCadastro" variant="secondary" dusk="screen-planos-teste-cadastro">Criar conta e começar o teste</x-button>
                @elseif ($urlTeste)
                    <x-button :href="$urlTeste" variant="secondary" :disabled="$personificando" dusk="screen-planos-teste-ativar">Começar teste grátis</x-button>
                @endif
            </x-card>
        @endif

        <section aria-labelledby="planos-titulo" class="mb-8" dusk="screen-planos-planos">
            <h2 id="planos-titulo" class="text-[length:var(--text-h2)] font-medium text-[color:var(--color-primary)] m-0 mb-3">Planos</h2>

            @if ($planos->isEmpty())
                <x-drm.aviso-inline variant="info" dusk="screen-planos-sem-planos">Não há planos à venda neste momento.</x-drm.aviso-inline>
            @else
                <fieldset class="m-0 mb-4 p-0 border-0 min-w-0" dusk="screen-planos-ciclo">
                    <legend class="sr-only">Ciclo de cobrança</legend>
                    <div class="inline-grid grid-cols-2 gap-1 rounded-md bg-[color:var(--color-neutral)]">
                        @foreach (['mensal' => 'Mensal', 'anual' => $economiaMax > 0 ? "Anual — economize {$economiaMax}%" : 'Anual'] as $valor => $rotulo)
                            <label class="relative block cursor-pointer">
                                <input type="radio" class="peer sr-only" name="ciclo" value="{{ $valor }}" wire:model.live="ciclo" dusk="screen-planos-ciclo-{{ $valor }}">
                                <span class="flex items-center justify-center min-h-[44px] rounded-md border-2 border-transparent px-3 py-2 text-sm font-medium
                                             text-[color:var(--color-secondary)]
                                             peer-checked:border-[color:var(--color-tertiary)] peer-checked:bg-[color:var(--color-surface)] peer-checked:text-[color:var(--color-primary)]
                                             peer-focus-visible:ring-2 peer-focus-visible:ring-[color:var(--color-ring)]">{{ $rotulo }}</span>
                            </label>
                        @endforeach
                    </div>
                </fieldset>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 list-none p-0 m-0" aria-live="polite">
                    @foreach ($planos as $plano)
                        @php
                            $atual = $plano->id === $planoAtualId;
                            $economia = VitrinePrecos::economiaPercentual($plano->preco_mensal_centavos, $plano->preco_anual_centavos);
                            $url = $atual ? null : $planoUrl($plano, $planoAtualId !== null);
                            $nomeBotao = ($planoAtualId !== null ? 'Trocar para o plano ' : 'Assinar plano ').$plano->nome.($anual ? ' anual' : ' mensal');
                        @endphp
                        <x-card as="article" class="h-full flex flex-col gap-2" aria-labelledby="plano-{{ $plano->id }}-nome" wire:key="plano-{{ $plano->id }}" dusk="screen-planos-plano-{{ $plano->id }}">
                            <h3 id="plano-{{ $plano->id }}-nome" class="text-[length:var(--text-h3)] font-medium text-[color:var(--color-primary)] m-0">{{ $plano->nome }}</h3>
                            @if ($anual)
                                <p class="m-0 text-[color:var(--color-primary)]" dusk="screen-planos-preco-{{ $plano->id }}">
                                    <span class="text-xl font-medium">{{ $brl($plano->preco_anual_centavos) }}</span> por ano
                                    <span class="block text-sm text-[color:var(--color-secondary)]">{{ $brl(VitrinePrecos::equivalenteMensal($plano->preco_anual_centavos)) }} por mês{{ $economia > 0 ? " · economia de {$economia}%" : '' }}</span>
                                </p>
                            @else
                                <p class="m-0 text-[color:var(--color-primary)]" dusk="screen-planos-preco-{{ $plano->id }}"><span class="text-xl font-medium">{{ $brl($plano->preco_mensal_centavos) }}</span> por mês</p>
                            @endif
                            <ul class="m-0 p-0 list-none text-[color:var(--color-secondary)]">
                                <li>{{ $analises($plano->cota_mensal) }} por mês</li>
                                <li>Empresas sem limite</li>
                                <li>Raio X do Negócio incluído</li>
                            </ul>
                            <div class="mt-auto pt-2">
                                @if ($atual)
                                    <span class="pill" dusk="screen-planos-seu-plano-{{ $plano->id }}">Seu plano</span>
                                @elseif (! $logado)
                                    <x-button :href="$urlCadastro" variant="primary" dusk="screen-planos-assinar-{{ $plano->id }}">Criar conta para assinar</x-button>
                                @elseif ($url)
                                    <x-button :href="$url" variant="primary" :disabled="$personificando" aria-label="{{ $nomeBotao }}" dusk="screen-planos-assinar-{{ $plano->id }}">{{ $planoAtualId !== null ? 'Trocar para este' : 'Assinar' }}</x-button>
                                @endif
                            </div>
                        </x-card>
                    @endforeach
                </div>
                <p class="mt-3 mb-0 text-sm text-[color:var(--color-secondary)]">Renova sozinho até você cancelar, em Meu plano.</p>
            @endif
        </section>

        @if ($pacotes->isNotEmpty())
            <section aria-labelledby="creditos-titulo" dusk="screen-planos-creditos">
                <h2 id="creditos-titulo" class="text-[length:var(--text-h2)] font-medium text-[color:var(--color-primary)] m-0 mb-1">Créditos avulsos</h2>
                <p class="m-0 mb-3 text-[color:var(--color-secondary)]" dusk="screen-planos-creditos-sem-raiox">Pague uma vez, use quando quiser. Não incluem o Raio X do Negócio.</p>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    @foreach ($pacotes as $pacote)
                        @php $url = $pacoteUrl($pacote); @endphp
                        <x-card as="article" class="h-full flex flex-col gap-2" aria-labelledby="pacote-{{ $pacote->id }}-nome" wire:key="pacote-{{ $pacote->id }}" dusk="screen-planos-pacote-{{ $pacote->id }}">
                            <h3 id="pacote-{{ $pacote->id }}-nome" class="text-[length:var(--text-h3)] font-medium text-[color:var(--color-primary)] m-0">{{ $pacote->nome }}</h3>
                            <p class="m-0 text-[color:var(--color-primary)]">{{ $analises($pacote->quantidade_creditos) }} · <span class="text-xl font-medium">{{ $brl($pacote->preco_centavos) }}</span></p>
                            <p class="m-0 text-sm text-[color:var(--color-secondary)]">{{ $brl(VitrinePrecos::unitario($pacote->preco_centavos, $pacote->quantidade_creditos)) }} por análise · válidas por {{ $pacote->validade_meses }} {{ $pacote->validade_meses === 1 ? 'mês' : 'meses' }}</p>
                            <p class="m-0 text-sm text-[color:var(--color-secondary)]">Não inclui o Raio X do Negócio</p>
                            <div class="mt-auto pt-2">
                                @if (! $logado)
                                    <x-button :href="$urlCadastro" variant="secondary" dusk="screen-planos-comprar-{{ $pacote->id }}">Criar conta para comprar</x-button>
                                @elseif ($url)
                                    <x-button :href="$url" variant="secondary" :disabled="$personificando" aria-label="Comprar pacote {{ $pacote->nome }}" dusk="screen-planos-comprar-{{ $pacote->id }}">Comprar</x-button>
                                @endif
                            </div>
                        </x-card>
                    @endforeach
                </div>
            </section>
        @endif
    @endif
</div>
