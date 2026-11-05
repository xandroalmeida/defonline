Olá, {{ $nome }}!

@if ($dias === 0)
Seu teste termina hoje.
@else
Seu teste termina em {{ $dataFim }} (em {{ $dias }} dias). Assine para continuar usando o que já faz.
@endif

Assinar um plano: {{ $link }}

Se você não assinar, nada é cobrado. Os diagnósticos que você já fez continuam disponíveis.

— Equipe DEFOnline
