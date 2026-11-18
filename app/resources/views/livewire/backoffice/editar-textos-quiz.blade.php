@php
    use App\Domain\Quiz\CatalogoPerguntas;
    use App\Livewire\Backoffice\EditarTextosQuiz;
    use App\Support\DataExibicao;

    $porBloco = collect($textos)->groupBy('bloco', preserveKeys: true);
    $atual = $editando ? $textos[$editando] : null;
    $editados = collect($textos)->where('editado', true)->count();

    // Contador "n / máx" que acompanha o que está sendo digitado.
    $contador = fn (string $valor, int $max) => mb_strlen($valor) . ' / ' . $max;
@endphp
<div>
    <x-page-header :title="'Textos do quiz'"
                   :subtitle="'O que o cliente lê em cada pergunta. Mudar o texto não muda o cálculo nem os diagnósticos já feitos.'">
        <x-slot:actions>
            @if (auth()->user()?->podeUsarBackoffice(\App\Support\PermissaoAdmin::VerRespostasQuiz))
                <x-button :href="route('backoffice.quiz.travamentos')" variant="secondary" size="sm">Onde os clientes travam</x-button>
            @endif
        </x-slot:actions>
    </x-page-header>

    <div class="grid gap-6 lg:grid-cols-[18rem_1fr] items-start">
        {{-- ============ Lista de perguntas ============ --}}
        <nav aria-label="Perguntas do quiz" class="card p-0 overflow-hidden lg:sticky lg:top-4" dusk="textos-lista">
            <p class="m-0 px-4 py-3 text-xs text-[color:var(--color-secondary)] border-b border-[color:var(--color-border)]">
                {{ count($textos) }} perguntas · {{ $editados }} {{ $editados === 1 ? 'editada' : 'editadas' }}
            </p>
            <div class="max-h-[40vh] lg:max-h-[70vh] overflow-y-auto">
                @foreach ($porBloco as $bloco => $perguntas)
                    <p class="m-0 px-4 pt-3 pb-1 text-xs font-semibold uppercase tracking-wider text-[color:var(--color-secondary)]">
                        {{ $bloco }}. {{ CatalogoPerguntas::BLOCOS[$bloco] }}
                    </p>
                    <ul class="list-none m-0 p-0 pb-2">
                        @foreach ($perguntas as $campo => $texto)
                            <li wire:key="item-{{ $campo }}">
                                <button type="button" wire:click="editar('{{ $campo }}')"
                                        @class([
                                            'w-full text-left flex items-start gap-2 px-4 py-2 text-sm border-0 cursor-pointer border-l-2',
                                            'bg-[color:var(--color-neutral)] border-[color:var(--color-tertiary)] font-medium text-[color:var(--color-primary)]' => $editando === $campo,
                                            'bg-transparent border-transparent text-[color:var(--color-primary)] hover:bg-[color:var(--color-neutral)]' => $editando !== $campo,
                                        ])
                                        @if ($editando === $campo) aria-current="true" @endif
                                        dusk="texto-editar-{{ $campo }}">
                                    <span class="w-10 shrink-0 text-xs text-[color:var(--color-secondary)] pt-0.5">{{ $campo === 'socios' ? '—' : $campo }}</span>
                                    <span class="flex-1 min-w-0">{{ $texto['rotulo'] }}</span>
                                    @if ($texto['editado'])
                                        <span class="shrink-0 mt-1.5 w-2 h-2 rounded-full bg-[color:var(--color-tertiary)]" title="Editado" aria-label="Editado" dusk="texto-editado-{{ $campo }}"></span>
                                    @endif
                                </button>
                            </li>
                        @endforeach
                    </ul>
                @endforeach
            </div>
        </nav>

        {{-- ============ Editor ============ --}}
        @if ($atual)
            <div class="flex flex-col gap-4" dusk="texto-{{ $editando }}">
                <x-card class="p-0">
                    <form wire:submit="salvar" novalidate>
                        {{-- Cabeçalho --}}
                        <div class="flex flex-wrap gap-3 justify-between items-start px-6 py-4 border-b border-[color:var(--color-border)]">
                            <div>
                                <p class="m-0 text-xs uppercase tracking-wider text-[color:var(--color-secondary)]">
                                    {{ $editando === 'socios' ? 'Sócios' : $editando }} · Bloco {{ $atual['bloco'] }} — {{ CatalogoPerguntas::BLOCOS[$atual['bloco']] }}
                                </p>
                                <h2 class="m-0 mt-1 text-lg font-semibold">{{ $atual['rotulo'] }}</h2>
                            </div>
                            @if ($atual['editado'])
                                <div class="flex items-center gap-2">
                                    <span class="pill text-xs">Editado</span>
                                    <x-botao-confirmar titulo="Restaurar texto original"
                                                       :mensagem="'Voltar o enunciado, o auxiliar e a ajuda desta pergunta para o texto original?'"
                                                       acao="restaurar('{{ $editando }}')"
                                                       dusk="texto-restaurar-{{ $editando }}">Restaurar original</x-botao-confirmar>
                                </div>
                            @else
                                <span class="pill text-xs">Texto original</span>
                            @endif
                        </div>

                        {{-- Campos --}}
                        <div class="flex flex-col divide-y divide-[color:var(--color-border)]">
                            <section class="px-6 py-4 flex flex-col gap-1.5">
                                <div class="flex justify-between items-baseline gap-2">
                                    <x-label for="texto-rotulo" class="mb-0">Enunciado</x-label>
                                    <span class="text-xs text-[color:var(--color-secondary)]">{{ $contador($rotulo, EditarTextosQuiz::ROTULO_MAX) }}</span>
                                </div>
                                <p class="m-0 text-xs text-[color:var(--color-secondary)]">A pergunta, em uma linha curta.</p>
                                <x-input type="text" id="texto-rotulo" wire:model.live.debounce.300ms="rotulo" maxlength="{{ EditarTextosQuiz::ROTULO_MAX }}" class="w-full" dusk="texto-rotulo"/>
                                @error('rotulo') <p class="text-[color:var(--color-destructive)] text-sm m-0" dusk="erro-texto-rotulo">{{ $message }}</p> @enderror
                                @if ($original['rotulo'] !== trim($rotulo))
                                    <p class="m-0 text-xs text-[color:var(--color-secondary)]" dusk="texto-original-rotulo">Original: “{{ $original['rotulo'] }}”</p>
                                @endif
                            </section>

                            <section class="px-6 py-4 flex flex-col gap-1.5">
                                <div class="flex justify-between items-baseline gap-2">
                                    <x-label for="texto-auxiliar" class="mb-0">Texto auxiliar</x-label>
                                    <span class="text-xs text-[color:var(--color-secondary)]">{{ $contador($auxiliar, EditarTextosQuiz::AUXILIAR_MAX) }}</span>
                                </div>
                                <p class="m-0 text-xs text-[color:var(--color-secondary)]">Aparece embaixo do campo, sempre visível. Opcional.</p>
                                <textarea id="texto-auxiliar" wire:model.live.debounce.300ms="auxiliar" rows="2" maxlength="{{ EditarTextosQuiz::AUXILIAR_MAX }}" class="input min-h-16 w-full" dusk="texto-auxiliar"></textarea>
                                @error('auxiliar') <p class="text-[color:var(--color-destructive)] text-sm m-0">{{ $message }}</p> @enderror
                                @if ($original['auxiliar'] !== trim($auxiliar))
                                    <p class="m-0 text-xs text-[color:var(--color-secondary)]">Original: {{ $original['auxiliar'] !== '' ? '“' . $original['auxiliar'] . '”' : '(vazio)' }}</p>
                                @endif
                            </section>

                            <section class="px-6 py-4 flex flex-col gap-1.5">
                                <div class="flex justify-between items-baseline gap-2">
                                    <x-label for="texto-ajuda" class="mb-0">Ajuda (?)</x-label>
                                    <span class="text-xs text-[color:var(--color-secondary)]">{{ $contador($ajuda, EditarTextosQuiz::AJUDA_MAX) }}</span>
                                </div>
                                <p class="m-0 text-xs text-[color:var(--color-secondary)]">Abre ao tocar no (?). Explique o conceito, dê um exemplo e diga onde achar o número. Use **texto** para negrito.</p>
                                <textarea id="texto-ajuda" wire:model.live.debounce.300ms="ajuda" rows="7" maxlength="{{ EditarTextosQuiz::AJUDA_MAX }}" class="input min-h-40 w-full" dusk="texto-ajuda"></textarea>
                                @error('ajuda') <p class="text-[color:var(--color-destructive)] text-sm m-0">{{ $message }}</p> @enderror
                                @if ($original['ajuda'] !== trim($ajuda))
                                    <details class="text-xs text-[color:var(--color-secondary)]">
                                        <summary class="cursor-pointer">Ver a ajuda original</summary>
                                        <p class="m-0 mt-1 whitespace-pre-line">{{ $original['ajuda'] !== '' ? $original['ajuda'] : '(vazia)' }}</p>
                                    </details>
                                @endif
                            </section>
                        </div>

                        {{-- Rodapé de ações --}}
                        <div class="flex flex-wrap gap-3 items-center px-6 py-4 border-t border-[color:var(--color-border)] bg-[color:var(--color-neutral)] rounded-b-lg">
                            <x-button type="submit" variant="primary" :disabled="! $alterado" dusk="texto-salvar">Salvar</x-button>
                            @if ($alterado)
                                <x-button type="button" variant="secondary" wire:click="descartar" dusk="texto-descartar">Descartar alterações</x-button>
                                <span class="text-sm text-[color:var(--color-secondary)]" dusk="texto-nao-salvo">Alterações não salvas.</span>
                            @elseif ($aviso)
                                <span class="text-sm text-[color:var(--color-secondary)]" role="status" dusk="texto-aviso">{{ $aviso }}</span>
                            @endif
                        </div>
                    </form>
                </x-card>

                {{-- Prévia --}}
                <section aria-labelledby="previa-titulo">
                    <h3 id="previa-titulo" class="text-sm font-semibold m-0 mb-2">Como o cliente vê</h3>
                    <div class="card p-6 bg-[color:var(--color-neutral)]" dusk="texto-previa">
                        <div class="card max-w-[32rem] p-4">
                            <div class="flex items-center gap-1.5 mb-1">
                                <x-label for="previa-campo" class="mb-0">{{ trim($rotulo) !== '' ? $rotulo : '—' }}</x-label>
                                <x-help id="previa" :text="$ajuda" :label="$rotulo"/>
                            </div>
                            <x-input type="text" id="previa-campo" disabled placeholder="Resposta do cliente" class="w-full"/>
                            @if (trim($auxiliar) !== '')
                                <p class="text-xs text-[color:var(--color-secondary)] mt-1 mb-0">{{ $auxiliar }}</p>
                            @endif
                        </div>
                        <p class="text-xs text-[color:var(--color-secondary)] mt-3 mb-0">Toque no (?) para ver a ajuda.</p>
                    </div>
                </section>

                {{-- Histórico --}}
                <details class="card p-4" @if ($historico->isNotEmpty()) open @endif>
                    <summary class="cursor-pointer text-sm font-semibold">Histórico ({{ $historico->count() }})</summary>
                    @if ($historico->isEmpty())
                        <p class="text-sm text-[color:var(--color-secondary)] m-0 mt-2">Nunca foi alterado: é o texto original.</p>
                    @else
                        <ol class="list-none m-0 mt-2 p-0 flex flex-col gap-2 text-sm" dusk="texto-historico">
                            @foreach ($historico as $log)
                                <li class="border-l-2 border-[color:var(--color-border)] pl-3">
                                    <span class="text-xs text-[color:var(--color-secondary)]">{{ DataExibicao::dataHora($log->occurred_at) }} · {{ $nomes[$log->actor_id] ?? '—' }}</span>
                                    <p class="m-0">{{ $log->action === 'quiz.texto_restaurado' ? 'Restaurou o texto original.' : 'Alterou o texto.' }}</p>
                                    @if (($log->before['rotulo'] ?? null) !== ($log->after['rotulo'] ?? null))
                                        <p class="m-0 text-xs text-[color:var(--color-secondary)]">Enunciado era: “{{ $log->before['rotulo'] ?? '' }}”</p>
                                    @endif
                                </li>
                            @endforeach
                        </ol>
                    @endif
                </details>
            </div>
        @endif
    </div>
</div>
