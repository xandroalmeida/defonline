@php
    use App\Domain\Quiz\CatalogoPerguntas;
    use App\Support\DataExibicao;
    use App\Support\RespostaQuiz;

    $porBloco = collect(CatalogoPerguntas::todas())->groupBy('bloco', preserveKeys: true);
    $aceitos = (array) ($payload['alertas_aceitos'] ?? []);
    $empresa = $diagnostico->empresa;
@endphp
<div>
    <x-page-header :title="'Respostas do quiz'"
                   :subtitle="($empresa?->nomeExibicao() ?? '—') . ' · diagnóstico de ' . DataExibicao::dataHora($diagnostico->gerado_em)">
        <x-slot:actions>
            @if ($veFichas && $empresa)
                <x-button :href="route('backoffice.empresas.ficha', $empresa->id)" variant="secondary" size="sm" dusk="respostas-ficha-empresa">Ficha da empresa</x-button>
            @endif
        </x-slot:actions>
    </x-page-header>

    <x-card class="mb-4 text-sm" dusk="respostas-cabecalho">
        <span class="text-[color:var(--color-secondary)]">Usuário:</span>
        {{ $diagnostico->usuario?->anonimizado() ? 'Anonimizado' : $diagnostico->usuario?->nome }}
        · <span class="text-[color:var(--color-secondary)]">Preenchido por:</span> {{ $diagnostico->personificado_por_id ? 'equipe (Logar como)' : 'cliente' }}
        · <span class="text-[color:var(--color-secondary)]">Motor:</span> {{ $diagnostico->motor_version }}
    </x-card>

    @foreach ($porBloco as $bloco => $perguntas)
        <section class="mb-4" aria-labelledby="bloco-{{ $bloco }}">
            <h2 id="bloco-{{ $bloco }}" class="text-base font-semibold mb-2">Bloco {{ $bloco }} — {{ CatalogoPerguntas::BLOCOS[$bloco] }}</h2>
            <div class="card p-0 overflow-x-auto">
                <table class="w-full text-sm text-left" dusk="respostas-bloco-{{ $bloco }}">
                    <tbody>
                        @foreach ($perguntas as $campo => $pergunta)
                            <tr class="border-t first:border-t-0 border-[color:var(--color-border)]" dusk="resposta-{{ $campo }}">
                                <th scope="row" class="px-3 py-2 font-normal text-[color:var(--color-secondary)] w-1/2">
                                    <span class="text-xs mr-1">{{ $campo === 'socios' ? '' : $campo }}</span>{{ $pergunta['rotulo'] }}
                                </th>
                                <td class="px-3 py-2 font-medium">{{ RespostaQuiz::formatar($pergunta['tipo'], $payload[$campo] ?? null) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @endforeach

    <section aria-labelledby="avisos-aceitos">
        <h2 id="avisos-aceitos" class="text-base font-semibold mb-2">Avisos de consistência aceitos</h2>
        @if ($aceitos === [])
            <x-card dusk="respostas-sem-avisos">Nenhum: o cliente não recebeu aviso ou corrigiu antes de seguir.</x-card>
        @else
            <ul class="card list-none m-0 p-3 flex flex-col gap-1 text-sm" dusk="respostas-avisos">
                @foreach ($aceitos as $aceito)
                    <li>
                        <span class="font-medium">{{ $aceito['regra'] ?? '—' }}</span> — {{ CatalogoPerguntas::regra((string) ($aceito['regra'] ?? '')) }}
                        @if (! empty($aceito['ocorrido_em']))
                            <span class="text-[color:var(--color-secondary)]">· aceito em {{ DataExibicao::dataHora($aceito['ocorrido_em']) }}</span>
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif
    </section>
</div>
