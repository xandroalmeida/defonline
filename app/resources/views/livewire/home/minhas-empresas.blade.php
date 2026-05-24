<div>
    <nav class="topo">
        <strong>DEFOnline</strong>
        <form method="POST" action="/logout" style="margin: 0;">
            @csrf
            <button type="submit" dusk="logout"
                    style="background: none; border: none; color: #2563eb; cursor: pointer; font: inherit;">
                Sair
            </button>
        </form>
    </nav>

    <div class="container container--wide minhas-empresas">
        <h1 dusk="saudacao">Olá, {{ $usuario->primeiroNome() }}</h1>

        @if ($empresas->isEmpty())
            <div class="vazio" dusk="minhas-empresas-vazio">
                <p>Você ainda não cadastrou nenhuma empresa.</p>
                <p class="info">Cadastre sua primeira Empresa para começar.</p>
                <a href="{{ route('empresas.nova') }}" class="botao-link"
                   dusk="minhas-empresas-cta-cadastrar">
                    Cadastrar primeira Empresa
                </a>
            </div>
        @else
            <h2 class="lista-titulo">Minhas Empresas</h2>
            <ul class="empresas" dusk="minhas-empresas-lista">
                @foreach ($empresas as $empresa)
                    <li class="empresa-card" dusk="minhas-empresas-item-{{ $empresa->id }}">
                        <div class="empresa-card__cabecalho">
                            <h3 class="empresa-card__nome" dusk="minhas-empresas-nome-{{ $empresa->id }}">
                                {{ $empresa->nome_fantasia ?: $empresa->razao_social }}
                            </h3>
                            <span class="pill pill--{{ $empresa->fonte_enriquecimento->value }}"
                                  dusk="minhas-empresas-fonte-{{ $empresa->id }}">
                                {{ $empresa->fonteBadge() }}
                            </span>
                        </div>
                        <p class="empresa-card__documento" dusk="minhas-empresas-doc-{{ $empresa->id }}">
                            {{ $empresa->documentoMascarado() }}
                        </p>
                        <p class="empresa-card__localizacao" dusk="minhas-empresas-local-{{ $empresa->id }}">
                            {{ $empresa->municipio }} / {{ $empresa->uf }}
                        </p>
                        <div class="empresa-card__acoes">
                            <button type="button"
                                    class="primary"
                                    disabled
                                    aria-disabled="true"
                                    title="Em breve — onda 2"
                                    dusk="minhas-empresas-diagnostico-{{ $empresa->id }}">
                                Iniciar diagnóstico
                            </button>
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>

    <style>
        .minhas-empresas h2.lista-titulo { font-size: 1.1rem; margin: 0 0 1rem; color: #1f2937; }
        .minhas-empresas .info { color: #6b7280; font-size: .95rem; }
        .minhas-empresas .vazio { text-align: center; padding: 1rem 0; }
        .minhas-empresas a.botao-link { display: inline-block; margin-top: .75rem; padding: .65rem 1rem; background: #2563eb; color: white; border-radius: 6px; text-decoration: none; font-weight: 600; }
        .minhas-empresas a.botao-link:hover { background: #1d4ed8; }
        .minhas-empresas ul.empresas { list-style: none; padding: 0; margin: 0; display: flex; flex-direction: column; gap: 1rem; }
        .minhas-empresas .empresa-card { background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 8px; padding: 1rem; }
        .minhas-empresas .empresa-card__cabecalho { display: flex; align-items: flex-start; justify-content: space-between; gap: .75rem; flex-wrap: wrap; }
        .minhas-empresas .empresa-card__nome { margin: 0; font-size: 1.05rem; color: #1f2937; }
        .minhas-empresas .empresa-card__documento,
        .minhas-empresas .empresa-card__localizacao { margin: .4rem 0 0; color: #4b5563; font-size: .92rem; }
        .minhas-empresas .pill { display: inline-block; padding: .2rem .7rem; border-radius: 999px; font-size: .8rem; font-weight: 600; white-space: nowrap; }
        .minhas-empresas .pill--rfb { background: #dbeafe; color: #1d4ed8; }
        .minhas-empresas .pill--manual { background: #f3f4f6; color: #4b5563; }
        .minhas-empresas .empresa-card__acoes { margin-top: 1rem; }
        .minhas-empresas .empresa-card__acoes button { width: auto; padding: .55rem 1rem; }
        .minhas-empresas .empresa-card__acoes button:disabled { background: #9ca3af; cursor: not-allowed; }
        @media (max-width: 480px) {
            .minhas-empresas .empresa-card__acoes button { width: 100%; }
        }
    </style>
</div>
