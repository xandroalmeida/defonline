@php
    use App\Domain\Cobranca\SituacaoDoPlano as S;
    use App\Domain\Cobranca\TipoDesconto;
    use App\Support\DataExibicao;

    $h2 = 'text-[length:var(--text-h3)] font-medium text-[color:var(--color-primary)] m-0 mb-3';
    $h3 = 'text-sm font-medium text-[color:var(--color-primary)] m-0 mb-2';
    $sutil = 'text-sm text-[color:var(--color-secondary)]';
    $erro = 'text-[color:var(--color-destructive)] text-sm m-0 mt-1';
    $reais = fn (int $centavos): string => 'R$ '.number_format($centavos / 100, 2, ',', '.');
    $dia = fn ($d): string => DataExibicao::data($d);
    $descontoDoCupom = fn (\App\Models\Cupom $c): string => $c->tipo === TipoDesconto::Percentual
        ? number_format($c->valor / 10, $c->valor % 10 === 0 ? 0 : 1, ',', '.').'% de desconto'
        : $reais($c->valor).' de desconto';
@endphp
<div class="max-w-[1100px]" x-data x-init="$nextTick(() => $refs.titulo?.focus())">
    <h1 x-ref="titulo" tabindex="-1" dusk="screen-conta-titulo"
        class="text-[length:var(--text-h1)] font-medium leading-tight tracking-tight text-[color:var(--color-primary)] m-0 mb-4">Minha conta</h1>

    @if ($personificando)
        <x-drm.aviso-inline variant="info" dusk="screen-conta-admin">Você está acessando como administrador. Aqui é só leitura.</x-drm.aviso-inline>
    @endif

    <div aria-live="polite">
        @if (session('conta-dados-salvos'))
            <x-drm.aviso-inline variant="info" dusk="screen-conta-dados-salvos">Dados atualizados.</x-drm.aviso-inline>
        @endif
        @if ($senhaAlterada)
            <x-drm.aviso-inline variant="info" dusk="screen-conta-senha-alterada">Senha alterada. As outras sessões foram encerradas.</x-drm.aviso-inline>
        @endif
    </div>

    <div class="flex flex-col gap-4 lg:grid lg:grid-cols-2 mb-6">
        <section aria-labelledby="dados-titulo" class="flex flex-col" dusk="screen-conta-dados">
            <x-card class="flex-1 flex flex-col">
                <h2 id="dados-titulo" class="{{ $h2 }}">Dados pessoais</h2>

                <form wire:submit="salvarDados" class="flex-1 flex flex-col gap-4 m-0" novalidate>
                    <div>
                        <x-label for="conta-nome">Nome completo</x-label>
                        <x-input type="text" id="conta-nome" wire:model="nome" autocomplete="name" :disabled="$personificando"
                                 aria-describedby="conta-nome-erro" dusk="screen-conta-nome"/>
                        @error('nome')<p id="conta-nome-erro" class="{{ $erro }}" dusk="screen-conta-nome-erro">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <x-label for="conta-telefone">Telefone WhatsApp</x-label>
                        <x-input-telefone id="conta-telefone" wire:model="telefone" :disabled="$personificando"
                                          aria-describedby="conta-telefone-erro" dusk="screen-conta-telefone"/>
                        @error('telefone')<p id="conta-telefone-erro" class="{{ $erro }}" dusk="screen-conta-telefone-erro">{{ $message }}</p>@enderror
                    </div>

                    <dl class="m-0 flex flex-col gap-4 text-sm">
                        <div>
                            <dt class="label">E-mail</dt>
                            <dd class="m-0 mt-0.5 flex flex-wrap items-center gap-2 text-[color:var(--color-primary)]">
                                <span class="break-all" dusk="screen-conta-email">{{ $usuario->email }}</span>
                                <span class="pill" dusk="screen-conta-email-situacao">{{ $usuario->emailConfirmado() ? 'Confirmado' : 'Não confirmado' }}</span>
                            </dd>
                        </div>
                        <div>
                            <dt class="label">CPF</dt>
                            <dd class="m-0 mt-0.5 text-[color:var(--color-primary)]" dusk="screen-conta-cpf">{{ $usuario->cpfMascarado() }}</dd>
                        </div>
                    </dl>
                    <p class="m-0 {{ $sutil }}">Para alterar o e-mail ou o CPF, escreva para eb@ebparcerias.com.</p>

                    <label class="flex gap-2.5 items-start text-sm font-normal cursor-pointer">
                        <input type="checkbox" wire:model.live="receberAvisoRenovacao" @disabled($personificando) dusk="screen-conta-aviso-renovacao"
                               class="mt-1 accent-[color:var(--color-tertiary)]">
                        <span class="text-[color:var(--color-primary)]">Receber por e-mail os avisos para renovar o diagnóstico</span>
                    </label>

                    @unless ($personificando)
                        <div class="mt-auto">
                            <x-button type="submit" variant="primary" wire:loading.attr="disabled" wire:target="salvarDados" dusk="screen-conta-salvar-dados">Salvar dados</x-button>
                        </div>
                    @endunless
                </form>
            </x-card>
        </section>

        <section aria-labelledby="senha-titulo" class="flex flex-col" dusk="screen-conta-senha">
            <x-card class="flex-1 flex flex-col">
                <h2 id="senha-titulo" class="{{ $h2 }}">Senha</h2>

                <form wire:submit="trocarSenha" class="flex-1 flex flex-col gap-4 m-0" novalidate>
                    <div>
                        <x-label for="conta-senha-atual">Senha atual</x-label>
                        <x-input type="password" id="conta-senha-atual" wire:model="senhaAtual" autocomplete="current-password" :disabled="$personificando"
                                 aria-describedby="conta-senha-atual-erro" dusk="screen-conta-senha-atual"/>
                        @error('senhaAtual')<p id="conta-senha-atual-erro" class="{{ $erro }}" dusk="screen-conta-senha-atual-erro">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <x-label for="conta-nova-senha">Nova senha <span class="normal-case font-normal tracking-normal text-[color:var(--color-secondary)]">(mín. 8 caracteres, com letras e números)</span></x-label>
                        <x-input type="password" id="conta-nova-senha" wire:model="novaSenha" autocomplete="new-password" :disabled="$personificando"
                                 aria-describedby="conta-nova-senha-erro" dusk="screen-conta-nova-senha"/>
                        @error('novaSenha')<p id="conta-nova-senha-erro" class="{{ $erro }}" dusk="screen-conta-nova-senha-erro">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <x-label for="conta-nova-senha-confirmacao">Confirme a nova senha</x-label>
                        <x-input type="password" id="conta-nova-senha-confirmacao" wire:model="novaSenha_confirmation" autocomplete="new-password" :disabled="$personificando"
                                 dusk="screen-conta-nova-senha-confirmacao"/>
                    </div>

                    @unless ($personificando)
                        <div class="mt-auto">
                            <x-button type="submit" variant="secondary" wire:loading.attr="disabled" wire:target="trocarSenha" dusk="screen-conta-trocar-senha">Trocar senha</x-button>
                        </div>
                    @endunless
                </form>
            </x-card>
        </section>

        @if ($cobrancaLigada)
            <section aria-labelledby="conta-plano-titulo" class="flex flex-col" dusk="screen-conta-plano">
                <x-card class="flex-1 flex flex-col">
                    <h2 id="conta-plano-titulo" class="{{ $h2 }}">Plano</h2>

                    @if ($situacao === S::SemPlano)
                        <p class="m-0 mb-3 text-sm text-[color:var(--color-primary)]">Você ainda não tem um plano.</p>
                        @if (Route::has('planos'))
                            <div class="mt-auto"><x-link :href="route('planos')" dusk="screen-conta-ver-planos">Ver planos e preços</x-link></div>
                        @endif
                    @else
                        <p class="m-0 mb-2 flex flex-wrap items-center gap-2 text-[color:var(--color-primary)]">
                            <span class="font-medium">
                                @if ($assinatura->trial) Teste gratuito
                                @else {{ $plano?->nome ?? 'Plano' }}@if ($ciclo) · {{ strtolower($ciclo->rotulo()) }}@endif
                                @endif
                            </span>
                            <span class="pill" dusk="screen-conta-situacao">{{ $situacao->rotulo() }}</span>
                        </p>

                        @if (in_array($situacao, [S::Teste, S::Ativo, S::Cancelado, S::EmAtraso], true))
                            <p class="m-0 mb-1 text-sm text-[color:var(--color-primary)]" dusk="screen-conta-cota">
                                <span class="text-[color:var(--color-secondary)]">Análises do plano</span>
                                {{ $assinatura->cota_restante_ciclo }} de {{ $assinatura->cota_snapshot }}
                            </p>
                        @endif

                        <p class="m-0 mb-1 text-sm text-[color:var(--color-primary)]" dusk="screen-conta-vencimento">
                            <span class="text-[color:var(--color-secondary)]">{{ match ($situacao) {
                                S::Ativo => 'Próximo vencimento',
                                S::EmAtraso => 'Venceu em',
                                S::Encerrado => 'Terminou em',
                                default => 'Acesso até',
                            } }}</span>
                            {{ $dia($vencimento) }}
                        </p>

                        @if ($cupomDoPlano !== null)
                            <p class="m-0 mb-1 text-sm text-[color:var(--color-primary)]" dusk="screen-conta-cupom-do-plano">
                                <span class="text-[color:var(--color-secondary)]">Cupom no plano</span> {{ $cupomDoPlano }}
                            </p>
                        @endif

                        <div class="mt-auto pt-3"><x-link :href="route('meu-plano')" dusk="screen-conta-meu-plano">Gerenciar em Meu plano</x-link></div>
                    @endif
                </x-card>
            </section>

            <section aria-labelledby="conta-creditos-titulo" class="flex flex-col" dusk="screen-conta-creditos">
                <x-card class="flex-1 flex flex-col">
                    <h2 id="conta-creditos-titulo" class="{{ $h2 }}">Créditos</h2>

                    @if ($creditos === 0)
                        <p class="m-0 mb-3 text-sm text-[color:var(--color-primary)]">Nenhum crédito disponível.</p>
                    @else
                        <p class="m-0 mb-1 text-sm text-[color:var(--color-primary)]">
                            <span class="font-medium" dusk="screen-conta-creditos-total">{{ $creditos }}</span>
                            {{ $creditos === 1 ? 'análise disponível' : 'análises disponíveis' }}
                        </p>
                        <p class="m-0 mb-3 {{ $sutil }}" dusk="screen-conta-creditos-validade">
                            {{ $proximoLote->quantidade_restante }} {{ $proximoLote->quantidade_restante === 1 ? 'vence' : 'vencem' }} em {{ $dia($proximoLote->data_expiracao) }}
                        </p>
                    @endif

                    @if (Route::has('creditos.comprar'))
                        <div class="mt-auto"><x-link :href="route('creditos.comprar')" dusk="screen-conta-comprar-creditos">Comprar créditos</x-link></div>
                    @endif
                </x-card>
            </section>

            <section aria-labelledby="conta-cupons-titulo" class="flex flex-col" dusk="screen-conta-cupons">
                <x-card class="flex-1 flex flex-col">
                    <h2 id="conta-cupons-titulo" class="{{ $h2 }}">Cupons</h2>

                    @if ($cuponsDisponiveis === [] && $cuponsUsados === [])
                        <p class="m-0 text-sm text-[color:var(--color-primary)]">Nenhum cupom na sua conta.</p>
                    @endif

                    @if ($cuponsDisponiveis !== [])
                        <h3 class="{{ $h3 }}">Disponíveis</h3>
                        <ul class="m-0 mb-1 p-0 list-none flex flex-col gap-2 text-sm">
                            @foreach ($cuponsDisponiveis as $c)
                                <li dusk="screen-conta-cupom-disponivel">
                                    <span class="font-medium text-[color:var(--color-primary)]">{{ $c->codigo }}</span>
                                    <span class="text-[color:var(--color-primary)]">· {{ $descontoDoCupom($c) }}</span>
                                    <span class="block {{ $sutil }}">Até {{ $dia($c->vigencia_fim) }}</span>
                                </li>
                            @endforeach
                        </ul>
                        <p class="m-0 mb-4 {{ $sutil }}">Entra sozinho na compra dos itens que ele cobre.</p>
                    @endif

                    @if ($cuponsUsados !== [])
                        <h3 class="{{ $h3 }}">Já usados</h3>
                        <ul class="m-0 p-0 list-none flex flex-col gap-2 text-sm">
                            @foreach ($cuponsUsados as $u)
                                <li dusk="screen-conta-cupom-usado">
                                    <span class="font-medium text-[color:var(--color-primary)]">{{ $u['codigo'] }}</span>
                                    <span class="text-[color:var(--color-primary)]">· {{ $reais($u['desconto_centavos']) }} de desconto</span>
                                    <span class="block {{ $sutil }}">Em {{ $dia($u['usado_em']) }}</span>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </x-card>
            </section>

            <section aria-labelledby="conta-cobrancas-titulo" class="flex flex-col" dusk="screen-conta-cobrancas">
                <x-card class="flex-1 flex flex-col">
                    <h2 id="conta-cobrancas-titulo" class="{{ $h2 }}">Últimas cobranças</h2>

                    @if ($cobrancasFalharam)
                        <p class="m-0 text-sm text-[color:var(--color-primary)]" role="alert" dusk="screen-conta-cobrancas-erro">Não conseguimos carregar as cobranças agora.</p>
                    @elseif ($cobrancas === [])
                        <p class="m-0 text-sm text-[color:var(--color-primary)]">Você ainda não fez nenhuma compra.</p>
                    @else
                        <ul class="m-0 mb-3 p-0 list-none flex flex-col gap-3 text-sm">
                            @foreach ($cobrancas as $c)
                                <li dusk="screen-conta-cobranca">
                                    <span class="flex flex-wrap items-center justify-between gap-2">
                                        <span class="text-[color:var(--color-primary)]">{{ $c['descricao'] }}</span>
                                        <span class="pill">{{ $c['status'] }}</span>
                                    </span>
                                    <span class="block {{ $sutil }}">{{ $dia($c['data']) }} · {{ $reais($c['valor_centavos']) }} · {{ $c['meio'] }}</span>
                                </li>
                            @endforeach
                        </ul>
                        <div class="mt-auto"><x-link :href="route('meu-plano')" dusk="screen-conta-todas-cobrancas">Ver todas em Meu plano</x-link></div>
                    @endif
                </x-card>
            </section>
        @endif
    </div>
</div>
