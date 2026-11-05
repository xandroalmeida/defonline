Olá, {{ $nome }}!

Seu desconto termina em {{ $dataFim }}. A partir daí o plano custa {{ $precoDepois }}.

@if ($semCartao)
Sem um cartão cadastrado, as funções pagas serão bloqueadas depois dessa data. Cadastre o cartão em Meu plano para continuar.

@endif
{{ $semCartao ? 'Cadastrar cartão' : 'Ver meu plano' }}: {{ $link }}

— Equipe DEFOnline
