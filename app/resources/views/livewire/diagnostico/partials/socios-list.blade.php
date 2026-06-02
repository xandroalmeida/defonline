{{--
    Lista dinâmica de sócios — STORY-038 (+ nome).
    Substitui os campos fixos Q21/Q22/Q23 por uma lista `socios[]` de objetos
    {cpf, nome} com 1+ obrigatório e cap de 10. Cada sócio é um bloco com nome +
    CPF empilhados e um botão remover no topo (mesma forma em desktop e mobile —
    CA-11). O "+ Adicionar sócio" vem abaixo, largura total no mobile.

    Vars herdadas do pai (quiz.blade.php): $socios (array), $maskCpf (JS).
--}}
<div class="flex flex-col gap-3" dusk="quiz-socios">
    <div class="flex items-center gap-1.5">
        <x-label class="mb-0">Sócios</x-label>
        <x-help id="socios" :text="config('quiz.help-industria.campos.socios')" label="Sócios"/>
    </div>
    <p class="text-sm text-[color:var(--color-secondary)] m-0">
        Informe nome e CPF de ao menos um sócio. Use "Adicionar sócio" para incluir os demais.
    </p>

    @foreach ($socios as $i => $socio)
        @php($erroNome = $errors->has('socios.'.$i.'.nome') ? 'erro-socio-'.$i.'-nome' : null)
        @php($erroCpf = $errors->has('socios.'.$i.'.cpf') ? 'erro-socio-'.$i.'-cpf' : null)
        @php($confirmarRemocao = filled(trim((string) ($socio['nome'] ?? '')) ?: trim((string) ($socio['cpf'] ?? '')))
            ? 'Remover o sócio '.(trim((string) ($socio['nome'] ?? '')) ?: trim((string) ($socio['cpf'] ?? ''))).' da lista?'
            : 'Remover esta linha de sócio?')
        <div class="rounded-md border border-[color:var(--color-border)] p-3 flex flex-col gap-2"
             wire:key="socio-{{ $i }}" dusk="quiz-socio-linha-{{ $i }}">
            <div class="flex items-center justify-between">
                <span class="text-xs font-semibold uppercase tracking-wider text-[color:var(--color-secondary)]">
                    Sócio {{ $i + 1 }}
                </span>
                <x-button type="button" variant="secondary" size="sm"
                          wire:click="removerSocio({{ $i }})"
                          wire:confirm="{{ $confirmarRemocao }}"
                          :disabled="count($socios) <= 1"
                          aria-label="Remover sócio {{ $i + 1 }}"
                          dusk="quiz-socio-remover-{{ $i }}">
                    {{-- width/height explícitos no SVG: independem de classe utilitária. --}}
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18"
                         fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"
                         aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round"
                              d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0"/>
                    </svg>
                    <span class="sr-only">Remover sócio {{ $i + 1 }}</span>
                </x-button>
            </div>

            {{-- Nome --}}
            <div>
                <x-label :for="'socio-'.$i.'-nome'" class="text-sm">Nome do sócio {{ $i + 1 }}</x-label>
                <x-input type="text"
                         id="socio-{{ $i }}-nome"
                         wire:model.live.debounce.300ms="socios.{{ $i }}.nome"
                         autocomplete="off"
                         maxlength="120"
                         placeholder="Nome completo"
                         :aria-describedby="$erroNome"
                         :aria-invalid="$erroNome ? 'true' : null"
                         dusk="quiz-socio-{{ $i }}-nome"/>
                @error('socios.'.$i.'.nome')
                    <p id="erro-socio-{{ $i }}-nome" class="text-[color:var(--color-destructive)] text-sm mt-1 mb-0"
                       dusk="quiz-erro-socio-{{ $i }}-nome">{{ $message }}</p>
                @enderror
            </div>

            {{-- CPF --}}
            <div>
                <x-label :for="'socio-'.$i.'-cpf'" class="text-sm">CPF do sócio {{ $i + 1 }}</x-label>
                <x-input type="text"
                         id="socio-{{ $i }}-cpf"
                         wire:model.live.debounce.300ms="socios.{{ $i }}.cpf"
                         inputmode="numeric" autocomplete="off"
                         maxlength="14"
                         placeholder="000.000.000-00"
                         :aria-describedby="$erroCpf"
                         :aria-invalid="$erroCpf ? 'true' : null"
                         x-data
                         x-on:input="{!! $maskCpf !!}"
                         dusk="quiz-socio-{{ $i }}-cpf"/>
                @error('socios.'.$i.'.cpf')
                    <p id="erro-socio-{{ $i }}-cpf" class="text-[color:var(--color-destructive)] text-sm mt-1 mb-0"
                       dusk="quiz-erro-socio-{{ $i }}-cpf">{{ $message }}</p>
                @enderror
            </div>
        </div>
    @endforeach

    <div>
        <x-button type="button" variant="secondary" size="sm"
                  class="w-full sm:w-auto"
                  wire:click="adicionarSocio"
                  :disabled="count($socios) >= 10"
                  aria-label="Adicionar sócio"
                  dusk="quiz-socio-adicionar">
            + Adicionar sócio
        </x-button>
        @if (count($socios) >= 10)
            <p class="text-sm text-[color:var(--color-secondary)] mt-1 mb-0" dusk="quiz-socio-limite">
                Limite de 10 sócios por empresa.
            </p>
        @endif
    </div>
</div>
