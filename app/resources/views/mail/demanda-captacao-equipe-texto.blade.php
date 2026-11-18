Nova demanda de captação. Retorno ao cliente até {{ $prazo }} (5 dias úteis).

{{ $empresa }} — {{ $documento }}
@if ($empresaEmail || $empresaTelefone)
Contato da empresa: {{ collect([$empresaEmail, $empresaTelefone])->filter()->join(' · ') }}
@endif
Usuário: {{ $usuarioNome }} · {{ $usuarioEmail }}@if ($usuarioTelefone) · {{ $usuarioTelefone }}@endif


Valor que precisa captar: {{ $valorCaptar ?? '—' }}
Endividamento total no mercado: {{ $endividamento ?? '—' }}
Venda mensal com cartão / duplicatas: {{ $vendaCartao ?? '—' }}
@if ($socios !== [])
Sócios: {{ implode(', ', $socios) }}
@endif

O cliente autorizou o contato pelo e-mail ou WhatsApp cadastrado.

Ver as demandas no backoffice: {{ $link }}
