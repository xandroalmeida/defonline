@php
    use App\Domain\Quiz\CatalogoPerguntas;
    use App\Livewire\Backoffice\TravamentosQuiz;

    $perguntas = \App\Support\TextosQuiz::todos();
@endphp
<div>
    <x-page-header :title="'Onde os clientes travam'"
                   :subtitle="'Erros de preenchimento e avisos de consistência do quiz, para simplificar e clarear as perguntas. Não conta o que a equipe fez pelo Logar como.'" />

    <div class="flex flex-wrap gap-3 items-end mb-4">
        <div>
            <x-label for="travamentos-periodo">Período</x-label>
            <x-select id="travamentos-periodo" wire:model.live="dias" dusk="travamentos-periodo">
                @foreach (TravamentosQuiz::PERIODOS as $valor => $rotulo)
                    <option value="{{ $valor }}">{{ $rotulo }}</option>
                @endforeach
            </x-select>
        </div>
        <p class="m-0 pb-2 text-sm text-[color:var(--color-secondary)]" dusk="travamentos-funil">
            {{ $iniciados }} {{ $iniciados === 1 ? 'quiz iniciado' : 'quizzes iniciados' }} · {{ $concluidos }} {{ $concluidos === 1 ? 'diagnóstico concluído' : 'diagnósticos concluídos' }}
        </p>
    </div>

    <section class="mb-6" aria-labelledby="por-pergunta">
        <h2 id="por-pergunta" class="text-base font-semibold mb-2">Erros por pergunta</h2>
        @if ($porCampo->isEmpty())
            <x-card dusk="travamentos-sem-erros">Nenhum erro de preenchimento registrado no período.</x-card>
        @else
            <div class="card p-0 overflow-x-auto">
                <table class="w-full text-sm text-left" dusk="travamentos-por-pergunta">
                    <thead class="text-[color:var(--color-secondary)]">
                        <tr>
                            <th scope="col" class="px-3 py-2">Pergunta</th>
                            <th scope="col" class="px-3 py-2">Bloco</th>
                            <th scope="col" class="px-3 py-2 text-right">Erros</th>
                            <th scope="col" class="px-3 py-2 text-right">Quizzes afetados</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($porCampo as $linha)
                            <tr class="border-t border-[color:var(--color-border)]" dusk="travamento-{{ $linha->chave }}">
                                <td class="px-3 py-2"><span class="text-xs text-[color:var(--color-secondary)] mr-1">{{ $linha->chave === 'socios' ? '' : $linha->chave }}</span>{{ \App\Support\TextosQuiz::rotulo((string) $linha->chave) }}</td>
                                <td class="px-3 py-2">{{ $perguntas[$linha->chave]['bloco'] ?? '—' }}</td>
                                <td class="px-3 py-2 text-right font-medium">{{ $linha->vezes }}</td>
                                <td class="px-3 py-2 text-right">{{ $linha->quizzes }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>

    <section aria-labelledby="por-regra">
        <h2 id="por-regra" class="text-base font-semibold mb-2">Avisos e bloqueios de consistência</h2>
        <div class="card p-0 overflow-x-auto">
            <table class="w-full text-sm text-left" dusk="travamentos-por-regra">
                <thead class="text-[color:var(--color-secondary)]">
                    <tr>
                        <th scope="col" class="px-3 py-2">Regra</th>
                        <th scope="col" class="px-3 py-2 text-right">Mostrado</th>
                        <th scope="col" class="px-3 py-2 text-right">Aceito e seguiu</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($regras as $regra => $linha)
                        <tr class="border-t border-[color:var(--color-border)]" dusk="travamento-regra-{{ $regra }}">
                            <td class="px-3 py-2"><span class="font-medium">{{ $regra }}</span> — {{ $linha['titulo'] }}@if ($linha['bloqueio']) <span class="pill text-xs ml-1">bloqueia</span>@endif</td>
                            <td class="px-3 py-2 text-right">{{ $linha['mostrado'] }}</td>
                            <td class="px-3 py-2 text-right">{{ $linha['bloqueio'] ? '—' : $linha['aceito'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <p class="text-xs text-[color:var(--color-secondary)] mt-2 mb-0">"Mostrado" conta desde a STORY-105 (18/11/2026). "Aceito e seguiu" vem dos diagnósticos e inclui os anteriores.</p>
    </section>
</div>
