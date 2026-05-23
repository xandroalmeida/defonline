@extends('layouts.app')

@section('conteudo')
<div class="container container--wide">
    <h1 dusk="empresa-show-titulo">{{ $empresa->razao_social }}</h1>

    <p class="badge" dusk="empresa-show-fonte">
        Fonte: {{ $empresa->fonte_enriquecimento->rotulo() }}
    </p>

    <dl class="empresa-show">
        <dt>Tipo do documento</dt>
        <dd dusk="empresa-show-tipo">{{ strtoupper($empresa->tipo_documento->value) }}</dd>

        <dt>{{ strtoupper($empresa->tipo_documento->value) }}</dt>
        <dd dusk="empresa-show-documento">{{ $empresa->documentoFormatado() }}</dd>

        <dt>Razão social</dt>
        <dd dusk="empresa-show-razao-social">{{ $empresa->razao_social }}</dd>

        <dt>Nome fantasia</dt>
        <dd dusk="empresa-show-nome-fantasia">{{ $empresa->nome_fantasia ?: '—' }}</dd>

        <dt>CNAE</dt>
        <dd dusk="empresa-show-cnae">{{ $empresa->cnae ?: '—' }}</dd>

        <dt>Município / UF</dt>
        <dd dusk="empresa-show-localizacao">{{ $empresa->municipio }} / {{ $empresa->uf }}</dd>

        <dt>Situação cadastral</dt>
        <dd dusk="empresa-show-situacao">{{ $empresa->situacao_cadastral->rotulo() }}</dd>

        <dt>Data de fundação</dt>
        <dd dusk="empresa-show-data-fundacao">{{ $empresa->data_fundacao?->format('d/m/Y') ?: '—' }}</dd>
    </dl>

    {{-- "Minhas Empresas" entra na STORY-016 — link inerte enquanto isso. --}}
    <a href="#" class="botao" dusk="empresa-show-voltar" aria-disabled="true"
       onclick="event.preventDefault(); return false;">Voltar para Minhas Empresas</a>
</div>
@endsection
