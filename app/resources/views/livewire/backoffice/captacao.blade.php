@php
    use App\Domain\Captacao\StatusDemanda;
    use App\Support\DataExibicao;

    $moeda = fn ($v) => $v === null ? '—' : 'R$ '.number_format((float) $v, 2, ',', '.');
    $telefone = function (?string $t): ?string {
        $d = preg_replace('/\D+/', '', (string) $t) ?? '';
        return match (strlen($d)) {
            11 => sprintf('(%s) %s-%s', substr($d, 0, 2), substr($d, 2, 5), substr($d, 7)),
            10 => sprintf('(%s) %s-%s', substr($d, 0, 2), substr($d, 2, 4), substr($d, 6)),
            0 => null,
            default => $d,
        };
    };
    $dias = \App\Domain\Captacao\PrazoRetorno::DIAS_UTEIS;
@endphp
<div x-data="{ arrastando: null, sobre: null }">
    <x-page-header :title="'Captação'"
                   :subtitle="'Demandas de captação do bloco 4. Retorno ao cliente em até ' . $dias . ' dias úteis. Arraste os cartões entre as etapas.'" />

    {{-- Configuração: fica recolhida para o quadro ganhar a tela. Abre sozinha enquanto não há e-mail;
         `wire:ignore.self` mantém aberta/fechada como a pessoa deixou depois de salvar. --}}
    <details wire:ignore.self class="card mb-4 p-4" @if (! $emailCadastrado) open @endif dusk="captacao-config">
        <summary class="cursor-pointer text-sm font-medium text-[color:var(--color-primary)]">
            E-mail da equipe:
            <span class="font-normal text-[color:var(--color-secondary)]" dusk="captacao-email-situacao">
                @if ($emailCadastrado)
                    cada demanda com contato autorizado é enviada para {{ $emailCadastrado }}.
                @else
                    sem e-mail cadastrado. As demandas ficam só neste quadro e no alerta da equipe.
                @endif
            </span>
        </summary>
        <form wire:submit="salvar" novalidate class="flex flex-wrap gap-3 items-end mt-3">
            <div class="flex-1 min-w-[16rem]">
                <x-label for="captacao-email">E-mail da equipe que recebe as demandas</x-label>
                <x-input type="email" id="captacao-email" wire:model.live.debounce.400ms="email" maxlength="255"
                         placeholder="Deixe em branco para não enviar" class="w-full" dusk="captacao-email"/>
                @error('email')
                    <p class="text-[color:var(--color-destructive)] text-sm mt-1 mb-0" dusk="erro-captacao-email">{{ $message }}</p>
                @enderror
            </div>
            <x-button type="submit" variant="primary" dusk="captacao-salvar">Salvar</x-button>
            @if ($salvo)
                <span class="text-sm text-[color:var(--color-secondary)] self-center" role="status" dusk="captacao-salvo">Salvo.</span>
            @endif
        </form>
    </details>

    {{-- Filtros --}}
    <div class="flex flex-wrap gap-3 items-end mb-4" dusk="captacao-filtros">
        <div class="flex-1 min-w-[14rem]">
            <x-label for="captacao-busca">Buscar empresa ou CNPJ/CPF</x-label>
            <x-input type="search" id="captacao-busca" wire:model.live.debounce.400ms="busca" class="w-full" dusk="captacao-busca"/>
        </div>
        <div>
            <x-label for="captacao-filtro-resp">Responsável</x-label>
            <x-select id="captacao-filtro-resp" wire:model.live="responsavel" dusk="captacao-filtro-responsavel">
                <option value="">Todos</option>
                <option value="meus">Meus</option>
                <option value="sem">Sem responsável</option>
                @foreach ($responsaveis as $r)
                    <option value="{{ $r->id }}">{{ $r->nome }}</option>
                @endforeach
            </x-select>
        </div>
        <label class="flex gap-2 items-center text-sm cursor-pointer pb-2">
            <input type="checkbox" wire:model.live="mostrarSemAutorizacao" class="accent-[color:var(--color-tertiary)]" dusk="captacao-filtro-sem-autorizacao">
            Mostrar sem autorização de contato ({{ $semAutorizacao }})
        </label>
    </div>

    {{-- Quadro: rola na horizontal no celular, 4 colunas lado a lado na tela larga. --}}
    <div class="flex gap-3 overflow-x-auto pb-2 lg:grid lg:grid-cols-4 lg:overflow-visible" dusk="captacao-quadro">
        @foreach ($colunas as $valor => $coluna)
            @php /** @var StatusDemanda $status */ $status = $coluna['status']; @endphp
            <section class="raia raia--{{ $valor }} min-w-[16rem] lg:min-w-0 flex-1 flex flex-col rounded-lg p-2 transition-shadow"
                     :class="sobre === '{{ $valor }}' && 'ring-2 ring-[color:var(--color-tertiary)]'"
                     aria-labelledby="coluna-{{ $valor }}"
                     x-on:dragover.prevent="sobre = '{{ $valor }}'"
                     x-on:dragleave="sobre = null"
                     x-on:drop.prevent="if (arrastando) { $wire.mover(arrastando, '{{ $valor }}') } arrastando = null; sobre = null"
                     dusk="captacao-coluna-{{ $valor }}">
                <h2 id="coluna-{{ $valor }}" class="raia__titulo m-0 mb-2 px-1 flex justify-between items-center text-sm font-semibold uppercase tracking-wide">
                    <span>{{ $status->rotulo() }}</span>
                    <span class="raia__total text-xs normal-case" dusk="captacao-total-{{ $valor }}">{{ $coluna['total'] }}</span>
                </h2>
                <ul class="list-none m-0 p-0 flex flex-col gap-2 min-h-16">
                    @forelse ($coluna['demandas'] as $demanda)
                        @php $atrasada = $demanda->atrasada(); @endphp
                        <li wire:key="cartao-{{ $demanda->id }}"
                            draggable="true"
                            x-on:dragstart="arrastando = '{{ $demanda->id }}'; $event.dataTransfer.effectAllowed = 'move'"
                            x-on:dragend="arrastando = null; sobre = null"
                            class="card p-3 cursor-grab active:cursor-grabbing {{ $atrasada ? 'cartao--atrasado' : '' }}"
                            dusk="captacao-cartao-{{ $demanda->id }}">
                            <button type="button" wire:click="abrir('{{ $demanda->id }}')"
                                    class="block w-full text-left bg-transparent border-0 p-0 cursor-pointer"
                                    dusk="captacao-abrir-{{ $demanda->id }}">
                                <span class="block font-medium text-[color:var(--color-primary)]">{{ $demanda->empresa->nomeExibicao() }}</span>
                                <span class="block text-sm text-[color:var(--color-secondary)]">{{ $moeda($demanda->valor_captar) }}</span>
                            </button>
                            <div class="mt-2 flex flex-wrap gap-x-2 gap-y-1 items-center text-xs text-[color:var(--color-secondary)]">
                                @if (! $demanda->autoriza_contato)
                                    <span class="pill" dusk="captacao-sem-autorizacao-{{ $demanda->id }}">Sem autorização</span>
                                @elseif ($status->emAberto() && $atrasada)
                                    <span class="font-semibold text-[color:var(--color-destructive-hover)]" dusk="captacao-atrasada-{{ $demanda->id }}">Atrasada · era até {{ DataExibicao::dia($demanda->prazoRetorno()) }}</span>
                                @elseif ($status->emAberto())
                                    <span>Até {{ DataExibicao::dia($demanda->prazoRetorno()) }}</span>
                                @endif
                                <span dusk="captacao-responsavel-{{ $demanda->id }}">{{ $demanda->responsavel?->nome ?? 'Sem responsável' }}</span>
                            </div>
                            {{-- Teclado e celular: mover sem arrastar. --}}
                            <div class="mt-2 flex justify-between">
                                @if ($anterior = $status->anterior())
                                    <x-button type="button" variant="secondary" size="sm" wire:click="mover('{{ $demanda->id }}', '{{ $anterior->value }}')"
                                              aria-label="Mover para {{ $anterior->rotulo() }}" dusk="captacao-voltar-{{ $demanda->id }}">← {{ $anterior->rotulo() }}</x-button>
                                @else
                                    <span></span>
                                @endif
                                @if ($proxima = $status->proxima())
                                    <x-button type="button" variant="secondary" size="sm" wire:click="mover('{{ $demanda->id }}', '{{ $proxima->value }}')"
                                              aria-label="Mover para {{ $proxima->rotulo() }}" dusk="captacao-avancar-{{ $demanda->id }}">{{ $proxima->rotulo() }} →</x-button>
                                @endif
                            </div>
                        </li>
                    @empty
                        <li class="text-sm text-[color:var(--color-secondary)] px-1 py-3 text-center" dusk="captacao-coluna-vazia-{{ $valor }}">Nenhuma demanda.</li>
                    @endforelse
                </ul>
                @if ($status === StatusDemanda::Encerrada && $coluna['total'] > count($coluna['demandas']))
                    <p class="text-xs text-[color:var(--color-secondary)] m-0 mt-2 px-1">Mostrando as {{ count($coluna['demandas']) }} mais recentes.</p>
                @endif
            </section>
        @endforeach
    </div>

    {{-- Detalhe do cartão. `wire:ignore.self`: o Livewire não pode tirar o `open` que o showModal() põe,
         senão o diálogo fecharia a cada atualização. --}}
    @if ($selecionada)
        <dialog wire:ignore.self x-init="$el.showModal()" x-on:close="$wire.fechar()" x-on:click.self="$el.close()"
                aria-labelledby="demanda-titulo"
                class="m-auto w-[calc(100%-2rem)] max-w-[40rem] max-h-[90vh] rounded-lg border border-[color:var(--color-border)] bg-[color:var(--color-surface)] p-0 text-[color:var(--color-primary)] shadow-md backdrop:bg-black/40"
                wire:key="detalhe-{{ $selecionada->id }}"
                dusk="captacao-detalhe">
            <div class="p-6 flex flex-col gap-4">
                <div class="flex justify-between gap-4 items-start">
                    <div>
                        <h2 id="demanda-titulo" class="text-lg font-medium m-0">{{ $selecionada->empresa->nomeExibicao() }}</h2>
                        <p class="m-0 text-sm text-[color:var(--color-secondary)]">{{ $selecionada->empresa->documentoFormatado() }} · recebida em {{ DataExibicao::dataHora($selecionada->created_at) }}</p>
                    </div>
                    <x-button type="button" variant="secondary" size="sm" x-on:click="$el.closest('dialog').close()" aria-label="Fechar" dusk="captacao-detalhe-fechar">Fechar</x-button>
                </div>

                <dl class="grid grid-cols-[auto_1fr] gap-x-4 gap-y-1 text-sm m-0">
                    <dt class="text-[color:var(--color-secondary)]">Valor a captar</dt><dd class="m-0">{{ $moeda($selecionada->valor_captar) }}</dd>
                    <dt class="text-[color:var(--color-secondary)]">Usuário</dt>
                    <dd class="m-0">{{ $selecionada->usuario->nome }} · <a class="link" href="mailto:{{ $selecionada->usuario->email }}">{{ $selecionada->usuario->email }}</a>@if ($telefone($selecionada->usuario->telefone)) · {{ $telefone($selecionada->usuario->telefone) }}@endif</dd>
                    @if ($selecionada->empresa->email || $selecionada->empresa->telefoneFormatado())
                        <dt class="text-[color:var(--color-secondary)]">Empresa</dt>
                        <dd class="m-0">{{ collect([$selecionada->empresa->email, $selecionada->empresa->telefoneFormatado()])->filter()->join(' · ') }}</dd>
                    @endif
                    <dt class="text-[color:var(--color-secondary)]">Contato</dt>
                    <dd class="m-0">{{ $selecionada->autoriza_contato ? 'Autorizado' : 'Não autorizado — não entre em contato' }}</dd>
                    @if ($selecionada->autoriza_contato)
                        <dt class="text-[color:var(--color-secondary)]">Retornar até</dt>
                        <dd class="m-0 {{ $selecionada->atrasada() ? 'font-semibold text-[color:var(--color-destructive)]' : '' }}">{{ DataExibicao::dia($selecionada->prazoRetorno()) }}{{ $selecionada->atrasada() ? ' (atrasada)' : '' }}</dd>
                    @endif
                </dl>

                <div class="flex flex-wrap gap-3">
                    <div>
                        <x-label for="detalhe-status">Etapa</x-label>
                        <x-select id="detalhe-status" dusk="captacao-detalhe-status"
                                x-on:change="$wire.mover('{{ $selecionada->id }}', $event.target.value)">
                            @foreach (StatusDemanda::cases() as $s)
                                <option value="{{ $s->value }}" @selected($selecionada->status === $s)>{{ $s->rotulo() }}</option>
                            @endforeach
                        </x-select>
                    </div>
                    <div>
                        <x-label for="detalhe-responsavel">Responsável</x-label>
                        <x-select id="detalhe-responsavel" dusk="captacao-detalhe-responsavel"
                                x-on:change="$wire.atribuir('{{ $selecionada->id }}', $event.target.value)">
                            <option value="">Sem responsável</option>
                            @foreach ($responsaveis as $r)
                                <option value="{{ $r->id }}" @selected($selecionada->responsavel_id === $r->id)>{{ $r->nome }}</option>
                            @endforeach
                        </x-select>
                    </div>
                </div>

                <form wire:submit="anotar" novalidate class="flex flex-col gap-2">
                    <x-label for="detalhe-nota">Nova nota</x-label>
                    <textarea id="detalhe-nota" wire:model="nota" rows="3" maxlength="{{ \App\Services\Captacao\GerirDemandaCaptacao::NOTA_MAX }}"
                              placeholder="Ex.: liguei, enviei proposta, aguardando documentos…" class="input min-h-20 w-full" dusk="captacao-nota"></textarea>
                    @error('nota')
                        <p class="text-[color:var(--color-destructive)] text-sm m-0" dusk="erro-captacao-nota">{{ $message }}</p>
                    @enderror
                    <div><x-button type="submit" variant="primary" size="sm" dusk="captacao-nota-salvar">Adicionar nota</x-button></div>
                </form>

                <section aria-labelledby="historico-titulo">
                    <h3 id="historico-titulo" class="text-sm font-semibold m-0 mb-2">Histórico</h3>
                    @if ($selecionada->eventos->isEmpty())
                        <p class="text-sm text-[color:var(--color-secondary)] m-0">Sem registros ainda.</p>
                    @else
                        <ol class="list-none m-0 p-0 flex flex-col gap-2" dusk="captacao-historico">
                            @foreach ($selecionada->eventos as $evento)
                                <li class="text-sm border-l-2 pl-3 {{ $evento->tipo === 'nota' ? 'border-[color:var(--color-tertiary)]' : 'border-[color:var(--color-border)]' }}">
                                    <span class="text-xs text-[color:var(--color-secondary)]">{{ DataExibicao::dataHora($evento->created_at) }} · {{ $evento->autor?->nome ?? '—' }}</span>
                                    <p class="m-0 whitespace-pre-line {{ $evento->tipo === 'nota' ? '' : 'text-[color:var(--color-secondary)]' }}">{{ $evento->texto }}</p>
                                </li>
                            @endforeach
                        </ol>
                    @endif
                </section>
            </div>
        </dialog>
    @endif
</div>
