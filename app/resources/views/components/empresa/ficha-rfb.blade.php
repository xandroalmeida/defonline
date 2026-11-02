{{--
    Ficha cadastral da RFB, só-leitura (STORY-063 CA-3/CA-4/CA-8; PDR-010).
    Usada no cadastro (antes de confirmar) e em /empresas/{id}.
    Campo ausente na resposta do provedor aparece como "Não informado pela Receita".
--}}
@props([
    'cnpj',
    'razaoSocial',
    'nomeFantasia' => null,
    'situacao',
    'dataAbertura' => null,
    'ficha',
])

@php
    use App\Domain\Rfb\DataRfb;
    use App\Domain\SituacaoCadastral;

    $ni = 'Não informado pela Receita';
    $end = $ficha->endereco;
    $linhaEndereco = trim(implode(', ', array_filter([$end->logradouro, $end->numero, $end->complemento])));
    $dataSituacao = DataRfb::formatar($ficha->dataSituacao);
    $rotuloRegime = function (\App\Domain\Rfb\OpcaoRegime $r) use ($ni): string {
        if ($r->optante === null) {
            return $ni;
        }
        $partes = [$r->rotulo()];
        if ($r->dataOpcao !== null) {
            $partes[] = 'opção em '.DataRfb::formatar($r->dataOpcao);
        }
        if ($r->dataExclusao !== null) {
            $partes[] = 'excluída em '.DataRfb::formatar($r->dataExclusao);
        }

        return implode(' — ', $partes);
    };
    $dt = 'text-xs uppercase tracking-wider font-semibold text-[color:var(--color-secondary)] sm:pt-0.5';
    $dd = 'm-0 text-[color:var(--color-primary)]';
@endphp

<div {{ $attributes->merge(['class' => 'flex flex-col gap-6']) }} dusk="ficha-rfb">
    @if ($situacao !== SituacaoCadastral::Ativa)
        <p class="text-sm rounded-md border border-[color:var(--color-farol-amarelo)] bg-[color:var(--color-neutral)] text-[color:var(--color-primary)] px-3 py-2 m-0"
           role="status" dusk="ficha-alerta-situacao">
            <strong>Atenção:</strong> empresa {{ mb_strtoupper($situacao->rotulo()) }} na Receita{{ $dataSituacao ? ' desde '.$dataSituacao : '' }}.
        </p>
    @endif

    <section aria-labelledby="ficha-identificacao">
        <h2 id="ficha-identificacao" class="text-base font-semibold m-0 mb-3 text-[color:var(--color-primary)]">Identificação</h2>
        <dl class="grid grid-cols-1 sm:grid-cols-[max-content_1fr] gap-x-6 gap-y-2 m-0">
            <dt class="{{ $dt }}">CNPJ</dt>
            <dd class="{{ $dd }}" dusk="ficha-cnpj">{{ \App\Domain\Cnpj::formatar($cnpj) }}</dd>
            <dt class="{{ $dt }}">Razão social</dt>
            <dd class="{{ $dd }}" dusk="ficha-razao-social">{{ $razaoSocial }}</dd>
            <dt class="{{ $dt }}">Nome fantasia</dt>
            <dd class="{{ $dd }}" dusk="ficha-nome-fantasia">{{ $nomeFantasia ?: $ni }}</dd>
            <dt class="{{ $dt }}">Situação cadastral</dt>
            <dd class="{{ $dd }}" dusk="ficha-situacao">{{ $situacao->rotulo() }}{{ $dataSituacao ? ' desde '.$dataSituacao : '' }}</dd>
            <dt class="{{ $dt }}">Data de abertura</dt>
            <dd class="{{ $dd }}" dusk="ficha-abertura">{{ DataRfb::formatar($dataAbertura) ?? $ni }}</dd>
            <dt class="{{ $dt }}">Natureza jurídica</dt>
            <dd class="{{ $dd }}" dusk="ficha-natureza">{{ $ficha->naturezaJuridica ?? $ni }}</dd>
            <dt class="{{ $dt }}">Porte</dt>
            <dd class="{{ $dd }}" dusk="ficha-porte">{{ $ficha->porte ?? $ni }}</dd>
            <dt class="{{ $dt }}">Capital social</dt>
            <dd class="{{ $dd }}" dusk="ficha-capital">{{ $ficha->capitalSocialFormatado() ?? $ni }}</dd>
        </dl>
    </section>

    <section aria-labelledby="ficha-regime">
        <h2 id="ficha-regime" class="text-base font-semibold m-0 mb-3 text-[color:var(--color-primary)]">Regime tributário</h2>
        <dl class="grid grid-cols-1 sm:grid-cols-[max-content_1fr] gap-x-6 gap-y-2 m-0">
            <dt class="{{ $dt }}">MEI (SIMEI)</dt>
            <dd class="{{ $dd }}" dusk="ficha-simei">{{ $rotuloRegime($ficha->simei) }}</dd>
            <dt class="{{ $dt }}">Simples Nacional</dt>
            <dd class="{{ $dd }}" dusk="ficha-simples">{{ $rotuloRegime($ficha->simples) }}</dd>
        </dl>
    </section>

    <section aria-labelledby="ficha-atividades">
        <h2 id="ficha-atividades" class="text-base font-semibold m-0 mb-3 text-[color:var(--color-primary)]">Atividades (CNAE)</h2>
        <dl class="grid grid-cols-1 sm:grid-cols-[max-content_1fr] gap-x-6 gap-y-2 m-0">
            <dt class="{{ $dt }}">Principal</dt>
            <dd class="{{ $dd }}" dusk="ficha-cnae-principal">
                @if ($ficha->cnaePrincipal)
                    {{ $ficha->cnaePrincipal->codigoFormatado() }} — {{ $ficha->cnaePrincipal->descricao ?? $ni }}
                @else
                    {{ $ni }}
                @endif
            </dd>
            <dt class="{{ $dt }}">Secundárias</dt>
            <dd class="{{ $dd }}" dusk="ficha-cnaes-secundarios">
                @if ($ficha->cnaesSecundarios === [])
                    Nenhuma atividade secundária na Receita
                @else
                    <ul class="m-0 pl-4 list-disc flex flex-col gap-1">
                        @foreach ($ficha->cnaesSecundarios as $atividade)
                            <li dusk="ficha-cnae-secundario">{{ $atividade->codigoFormatado() }} — {{ $atividade->descricao ?? $ni }}</li>
                        @endforeach
                    </ul>
                @endif
            </dd>
        </dl>
    </section>

    <section aria-labelledby="ficha-endereco">
        <h2 id="ficha-endereco" class="text-base font-semibold m-0 mb-3 text-[color:var(--color-primary)]">Endereço</h2>
        <dl class="grid grid-cols-1 sm:grid-cols-[max-content_1fr] gap-x-6 gap-y-2 m-0">
            <dt class="{{ $dt }}">Logradouro</dt>
            <dd class="{{ $dd }}" dusk="ficha-logradouro">{{ $linhaEndereco !== '' ? $linhaEndereco : $ni }}</dd>
            <dt class="{{ $dt }}">Bairro</dt>
            <dd class="{{ $dd }}" dusk="ficha-bairro">{{ $end->bairro ?? $ni }}</dd>
            <dt class="{{ $dt }}">CEP</dt>
            <dd class="{{ $dd }}" dusk="ficha-cep">{{ $end->cepFormatado() ?? $ni }}</dd>
            <dt class="{{ $dt }}">Município / UF</dt>
            <dd class="{{ $dd }}" dusk="ficha-municipio">{{ $end->municipio ?? $ni }} / {{ $end->uf ?? $ni }}</dd>
        </dl>
    </section>

    <section aria-labelledby="ficha-socios">
        <h2 id="ficha-socios" class="text-base font-semibold m-0 mb-3 text-[color:var(--color-primary)]">Sócios e administradores</h2>
        @if ($ficha->socios === [])
            <p class="m-0 text-[color:var(--color-secondary)]" dusk="ficha-sem-socios">Nenhum sócio ou administrador informado pela Receita.</p>
        @else
            <ul class="m-0 p-0 list-none flex flex-col gap-3">
                @foreach ($ficha->socios as $socio)
                    <li class="rounded-md border border-[color:var(--color-border)] px-3 py-2" dusk="ficha-socio">
                        <p class="m-0 font-semibold text-[color:var(--color-primary)]">{{ $socio->nome }}</p>
                        <p class="m-0 text-sm text-[color:var(--color-secondary)]">
                            {{ $socio->qualificacao ?? $ni }}
                            @if ($socio->documento) · {{ $socio->documento }} @endif
                            @if ($socio->dataEntrada) · desde {{ DataRfb::formatar($socio->dataEntrada) }} @endif
                        </p>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>
</div>
