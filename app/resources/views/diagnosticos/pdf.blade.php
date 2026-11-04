@php
    use App\Support\Relatorio\IndicadorFormatter;
    use App\Support\Relatorio\ValoresNominais;

    /**
     * PDF do diagnóstico — renderizado pelo dompdf (App\Support\Relatorio\PdfDiagnostico).
     * Mesma fonte de dados da tela (`diagnosticos.show`): o snapshot gravado,
     * nunca recálculo (IDR-010 §sub-decisão 4).
     *
     * @var \App\Models\Diagnostico $diagnostico
     * @var string $css      pdf.css com os tokens já resolvidos + @font-face da Inter
     * @var string $logoSvg  data URI do logo nas cores dos tokens
     */
    $empresa = $diagnostico->empresa;
    $nomeEmpresa = $empresa->nome_fantasia ?: $empresa->razao_social;
    $snapshot = (array) $diagnostico->indicadores_calculados;
    $nominais = ValoresNominais::doSnapshot((array) $diagnostico->quiz_payload, $diagnostico->motor_version);

    $resumo = (array) ($diagnostico->resumo_executivo ?? []);
    $resumoEhValido = isset($resumo['veredito']);
    $veredito = $resumo['veredito'] ?? 'fallback';
    $corVeredito = match ($veredito) {
        'saudavel' => 'verde',
        'precisa_atencao' => 'amarelo',
        'em_alerta' => 'vermelho',
        default => 'nenhum',
    };
    $rotuloFarol = [
        'verde' => 'Verde',
        'amarelo' => 'Amarelo',
        'vermelho' => 'Vermelho',
        'nenhum' => 'Sem classificação',
    ];
    $rotuloVeredito = [
        'saudavel' => 'Saudável',
        'precisa_atencao' => 'Precisa de atenção',
        'em_alerta' => 'Em alerta',
    ][$veredito] ?? null;

    $essenciais = array_values(array_filter(
        IndicadorFormatter::ORDEM_ESSENCIAIS,
        fn (string $c): bool => isset($snapshot[$c]),
    ));
    $ncgAbsoluto = $snapshot['ncg_absoluto'] ?? null;
    $informativos = array_filter([
        'ncg_absoluto' => $ncgAbsoluto,
    ]);

    $setor = ['industria' => 'Indústria', 'comercio' => 'Comércio', 'servicos' => 'Serviços'][$diagnostico->setor]
        ?? ucfirst((string) $diagnostico->setor);
    $rotuloDocumento = $empresa->tipo_documento->value === 'cpf' ? 'CPF' : 'CNPJ';
@endphp
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Diagnóstico — {{ $nomeEmpresa }} — {{ $diagnostico->gerado_em->format('d/m/Y') }}</title>
    <style>{!! $css !!}</style>
</head>
<body>

{{-- ═══ Capa ═══════════════════════════════════════════════════════════ --}}
<div class="capa">
    <div class="capa__marca"><img src="{{ $logoSvg }}" width="32" height="32" alt="">DEFOnline</div>

    <div class="capa__corpo">
        <div class="capa__filete"></div>
        <p class="capa__sobretitulo">Relatório de diagnóstico</p>
        <h1 class="capa__titulo">Diagnóstico<br>Econômico-Financeiro</h1>

        <p class="capa__empresa">{{ $nomeEmpresa }}</p>
        @if ($empresa->nome_fantasia && $empresa->nome_fantasia !== $empresa->razao_social)
            <p class="capa__razao">{{ $empresa->razao_social }}</p>
        @else
            <p class="capa__razao">&nbsp;</p>
        @endif

        <table class="capa__dados">
            <tr>
                <td class="rotulo">{{ $rotuloDocumento }}</td>
                <td>{{ $empresa->documentoFormatado() }}</td>
            </tr>
            <tr>
                <td class="rotulo">Localização</td>
                <td>{{ $empresa->municipio }} / {{ $empresa->uf }}</td>
            </tr>
            <tr>
                <td class="rotulo">Setor</td>
                <td>{{ $setor }}</td>
            </tr>
            <tr>
                <td class="rotulo">Data do diagnóstico</td>
                <td>{{ $diagnostico->gerado_em->format('d/m/Y') }}</td>
            </tr>
            @if ($diagnostico->personificadoPor)
                {{-- STORY-066 CA-6 --}}
                <tr>
                    <td class="rotulo">Feito por</td>
                    <td>{{ $diagnostico->personificadoPor->nome }}, da equipe DEFOnline</td>
                </tr>
            @endif
        </table>

        @if ($resumoEhValido && empty($resumo['fallback_acionado']) && $rotuloVeredito)
            <div class="capa__veredito borda-{{ $corVeredito }}">
                <span class="farol farol--{{ $corVeredito }}"><span class="farol__ponto"></span>{{ $rotuloVeredito }}</span>
                <p>{{ $resumo['veredito_texto'] ?? '' }}</p>
            </div>
        @endif
    </div>

    <div class="capa__rodape">
        Versão MVP · motor v{{ $diagnostico->motor_version }} · matriz {{ $diagnostico->matrix_version }}
        · gerado em {{ $diagnostico->gerado_em->format('d/m/Y H:i') }}<br>
        Este diagnóstico é uma ferramenta de apoio à gestão. Decisões financeiras devem considerar análise integral do negócio.
    </div>
</div>

{{-- ═══ Resumo executivo ═══════════════════════════════════════════════ --}}
@if ($resumoEhValido)
    <div class="secao manter-junto">
        <h2 class="secao__titulo">Resumo executivo</h2>
        <p class="secao__intro">Leitura geral dos indicadores e os pontos que mais pesaram no resultado.</p>

        <div class="resumo borda-{{ $corVeredito }}">
            @if (! empty($resumo['fallback_acionado']))
                <p>{{ $resumo['mensagem_fallback'] ?? '' }}</p>
            @else
                <span class="farol farol--{{ $corVeredito }}"><span class="farol__ponto"></span>{{ $rotuloVeredito ?? $rotuloFarol[$corVeredito] }}</span>
                <p class="resumo__veredito">{{ $resumo['veredito_texto'] ?? '' }}</p>

                @if (! empty($resumo['mensagem_extra']))
                    <p style="margin-bottom: 2.5mm">{{ $resumo['mensagem_extra'] }}</p>
                @endif

                @if (! empty($resumo['destaques_negativos']) || ! empty($resumo['destaque_positivo']))
                    <ul class="resumo__lista">
                        @foreach ($resumo['destaques_negativos'] ?? [] as $d)
                            <li>–&nbsp;&nbsp;&nbsp;{{ $d['texto'] }}</li>
                        @endforeach
                        @if (! empty($resumo['destaque_positivo']))
                            <li>+&nbsp;&nbsp;&nbsp;{{ $resumo['destaque_positivo']['texto'] }}</li>
                        @endif
                    </ul>
                @endif

                @if (! empty($resumo['linha_fixa']))
                    <p class="secundario italico" style="font-size: 8.5pt">Os detalhes e recomendações de cada indicador estão nas páginas seguintes.</p>
                @endif
            @endif
        </div>
    </div>
@endif

{{-- ═══ Indicadores essenciais ═════════════════════════════════════════ --}}
@if (count($essenciais) > 0)
    <div class="secao">
        {{-- O título não fica órfão: `page-break-after: avoid` o prende ao thead,
             e o thead se repete no topo de cada página da tabela. --}}
        <div class="titulo-da-tabela">
            <h2 class="secao__titulo">Indicadores essenciais</h2>
            <p class="secao__intro">Os {{ count($essenciais) }} indicadores com farol, na ordem da matriz de recomendações.</p>
        </div>
        <table class="indicadores" data-secao="indicadores">
            <thead>
                <tr>
                    <th class="col-nome">Indicador</th>
                    <th class="col-valor">Valor</th>
                    <th class="col-farol">Farol</th>
                    <th class="col-msg">Recomendação</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($essenciais as $codigo)
                    @php
                        $ind = $snapshot[$codigo];
                        $valor = $ind['valor'] ?? null;
                        $farol = $ind['farol'] ?? 'nenhum';
                        $nom = $nominais->paraIndicador($codigo);
                    @endphp
                    <tr @class(['indisponivel' => $valor === null])>
                        <td class="nome">{{ IndicadorFormatter::nome($codigo) }}</td>
                        <td>
                            @if ($valor === null)
                                <span class="italico">Indisponível</span>
                            @else
                                <span class="valor">{{ IndicadorFormatter::valor($codigo, $valor, $diagnostico->motor_version) }}</span>
                                @if (! empty($nom))
                                    <div class="nominais">
                                        @foreach ($nom as $t)
                                            {{ $t['rotulo'] }}: <span style="white-space: nowrap">{{ $t['valor'] }}</span><br>
                                        @endforeach
                                    </div>
                                @endif
                            @endif
                        </td>
                        <td>
                            @if ($valor !== null)
                                <span class="farol farol--{{ $farol }}"><span class="farol__ponto"></span>{{ $rotuloFarol[$farol] ?? $rotuloFarol['nenhum'] }}</span>
                            @else
                                <span class="secundario">—</span>
                            @endif
                        </td>
                        <td class="mensagem">{{ $ind['mensagem'] ?? '' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif

{{-- ═══ Indicadores informativos ═══════════════════════════════════════ --}}
@if (count($informativos) > 0)
    <div class="secao manter-junto">
        <h2 class="secao__titulo">Indicadores informativos</h2>
        <p class="secao__intro">Sem farol — ajudam a dimensionar a necessidade de capital de giro.</p>
        <table class="informativos">
            <tr>
                @foreach ($informativos as $codigo => $ind)
                    @if (! $loop->first)
                        <td class="espaco"></td>
                    @endif
                    <td>
                        <div class="card">
                            <p class="rotulo">Indicador informativo</p>
                            <p class="card__nome">{{ IndicadorFormatter::nome($codigo) }}</p>
                            <p class="card__valor">
                                @if (($ind['valor'] ?? null) === null)
                                    <span class="secundario italico" style="font-size: 11pt">Indisponível</span>
                                @else
                                    {{ IndicadorFormatter::valor($codigo, $ind['valor']) }}
                                @endif
                            </p>
                            @php $nom = $nominais->paraIndicador($codigo); @endphp
                            @if (! empty($nom) && ($ind['valor'] ?? null) !== null)
                                <div class="nominais" style="margin: -1mm 0 2mm">
                                    @foreach ($nom as $t)
                                        {{ $t['rotulo'] }}: <span style="white-space: nowrap">{{ $t['valor'] }}</span><br>
                                    @endforeach
                                </div>
                            @endif
                            <p class="card__mensagem">{{ $ind['mensagem'] ?? '' }}</p>
                        </div>
                    </td>
                @endforeach
            </tr>
        </table>
    </div>
@endif

</body>
</html>
