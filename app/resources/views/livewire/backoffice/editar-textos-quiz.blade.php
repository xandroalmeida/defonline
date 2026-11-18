@php
    use App\Domain\Quiz\CatalogoPerguntas;
    use App\Livewire\Backoffice\EditarTextosQuiz;
    use App\Support\DataExibicao;

    $porBloco = collect($textos)->groupBy('bloco', preserveKeys: true);
@endphp
<div>
    <x-page-header :title="'Textos do quiz'"
                   :subtitle="'Enunciado, texto auxiliar e ajuda (?) de cada pergunta, como o cliente vê. Mudar o texto não muda o cálculo nem os diagnósticos já feitos.'">
        <x-slot:actions>
            @if (auth()->user()?->podeUsarBackoffice(\App\Support\PermissaoAdmin::VerRespostasQuiz))
                <x-button :href="route('backoffice.quiz.travamentos')" variant="secondary" size="sm">Onde os clientes travam</x-button>
            @endif
        </x-slot:actions>
    </x-page-header>

    @foreach ($porBloco as $bloco => $perguntas)
        <section class="mb-6" aria-labelledby="textos-bloco-{{ $bloco }}">
            <h2 id="textos-bloco-{{ $bloco }}" class="text-base font-semibold mb-2">Bloco {{ $bloco }} — {{ CatalogoPerguntas::BLOCOS[$bloco] }}</h2>
            <div class="flex flex-col gap-2">
                @foreach ($perguntas as $campo => $texto)
                    <x-card class="p-4" wire:key="texto-{{ $campo }}" dusk="texto-{{ $campo }}">
                        @if ($editando === $campo)
                            {{-- Edição com prévia --}}
                            <form wire:submit="salvar" novalidate class="grid gap-4 lg:grid-cols-2">
                                <div class="flex flex-col gap-3">
                                    <div>
                                        <x-label for="texto-rotulo">Enunciado <span class="normal-case font-normal">({{ $campo }})</span></x-label>
                                        <x-input type="text" id="texto-rotulo" wire:model.live.debounce.300ms="rotulo" maxlength="{{ EditarTextosQuiz::ROTULO_MAX }}" class="w-full" dusk="texto-rotulo"/>
                                        @error('rotulo') <p class="text-[color:var(--color-destructive)] text-sm mt-1 mb-0" dusk="erro-texto-rotulo">{{ $message }}</p> @enderror
                                    </div>
                                    <div>
                                        <x-label for="texto-auxiliar">Texto auxiliar (embaixo do campo)</x-label>
                                        <textarea id="texto-auxiliar" wire:model.live.debounce.300ms="auxiliar" rows="2" maxlength="{{ EditarTextosQuiz::AUXILIAR_MAX }}" class="input min-h-16 w-full" dusk="texto-auxiliar"></textarea>
                                        @error('auxiliar') <p class="text-[color:var(--color-destructive)] text-sm mt-1 mb-0">{{ $message }}</p> @enderror
                                    </div>
                                    <div>
                                        <x-label for="texto-ajuda">Ajuda (?) — use **texto** para negrito</x-label>
                                        <textarea id="texto-ajuda" wire:model.live.debounce.300ms="ajuda" rows="6" maxlength="{{ EditarTextosQuiz::AJUDA_MAX }}" class="input min-h-32 w-full" dusk="texto-ajuda"></textarea>
                                        @error('ajuda') <p class="text-[color:var(--color-destructive)] text-sm mt-1 mb-0">{{ $message }}</p> @enderror
                                    </div>
                                    <div class="flex flex-wrap gap-2">
                                        <x-button type="submit" variant="primary" dusk="texto-salvar">Salvar</x-button>
                                        <x-button type="button" variant="secondary" wire:click="cancelar" dusk="texto-cancelar">Cancelar</x-button>
                                    </div>
                                </div>

                                <div class="flex flex-col gap-3">
                                    <div class="rounded-lg border border-dashed border-[color:var(--color-border)] p-4" dusk="texto-previa">
                                        <p class="text-xs uppercase tracking-wider text-[color:var(--color-secondary)] m-0 mb-3">Prévia</p>
                                        <div class="flex items-center gap-1.5 mb-1">
                                            <x-label for="previa-campo" class="mb-0">{{ $rotulo !== '' ? $rotulo : '—' }}</x-label>
                                            <x-help id="previa" :text="$ajuda" :label="$rotulo"/>
                                        </div>
                                        <x-input type="text" id="previa-campo" disabled placeholder="Resposta do cliente" class="w-full"/>
                                        @if (trim($auxiliar) !== '')
                                            <p class="text-xs text-[color:var(--color-secondary)] mt-1 mb-0">{{ $auxiliar }}</p>
                                        @endif
                                        <p class="text-xs text-[color:var(--color-secondary)] mt-3 mb-0">Clique no (?) para ver a ajuda.</p>
                                    </div>

                                    <div>
                                        <h3 class="text-sm font-semibold m-0 mb-2">Histórico</h3>
                                        @if ($historico->isEmpty())
                                            <p class="text-sm text-[color:var(--color-secondary)] m-0">Nunca foi alterado: é o texto original.</p>
                                        @else
                                            <ol class="list-none m-0 p-0 flex flex-col gap-1 text-sm" dusk="texto-historico">
                                                @foreach ($historico as $log)
                                                    <li>
                                                        <span class="text-[color:var(--color-secondary)]">{{ DataExibicao::dataHora($log->occurred_at) }} · {{ $nomes[$log->actor_id] ?? '—' }}</span>
                                                        — {{ $log->action === 'quiz.texto_restaurado' ? 'restaurou o original' : 'alterou' }}
                                                        @if (($log->before['rotulo'] ?? null) !== ($log->after['rotulo'] ?? null))
                                                            <div class="text-xs text-[color:var(--color-secondary)]">Enunciado era: “{{ $log->before['rotulo'] ?? '' }}”</div>
                                                        @endif
                                                    </li>
                                                @endforeach
                                            </ol>
                                        @endif
                                    </div>
                                </div>
                            </form>
                        @else
                            <div class="flex flex-wrap gap-3 justify-between items-start">
                                <div class="min-w-0 flex-1">
                                    <p class="m-0 font-medium">
                                        <span class="text-xs text-[color:var(--color-secondary)] mr-1">{{ $campo }}</span>{{ $texto['rotulo'] }}
                                        @if ($texto['editado'])
                                            <span class="pill text-xs ml-1" dusk="texto-editado-{{ $campo }}">Editado</span>
                                        @endif
                                    </p>
                                    @if ($texto['auxiliar'] !== '')
                                        <p class="m-0 mt-1 text-sm text-[color:var(--color-secondary)]">{{ $texto['auxiliar'] }}</p>
                                    @endif
                                    @if ($texto['ajuda'] !== '')
                                        <p class="m-0 mt-1 text-xs text-[color:var(--color-secondary)] line-clamp-2">(?) {{ str_replace('**', '', $texto['ajuda']) }}</p>
                                    @endif
                                </div>
                                <div class="flex gap-2">
                                    @if ($texto['editado'])
                                        <x-botao-confirmar titulo="Restaurar texto original"
                                                           :mensagem="'Voltar o enunciado, o auxiliar e a ajuda de ' . $campo . ' para o texto original?'"
                                                           acao="restaurar('{{ $campo }}')"
                                                           dusk="texto-restaurar-{{ $campo }}">Restaurar original</x-botao-confirmar>
                                    @endif
                                    <x-button type="button" variant="secondary" size="sm" wire:click="editar('{{ $campo }}')" dusk="texto-editar-{{ $campo }}">Editar</x-button>
                                </div>
                            </div>
                        @endif
                    </x-card>
                @endforeach
            </div>
        </section>
    @endforeach
</div>
