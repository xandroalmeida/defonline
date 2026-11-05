<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Seu desconto está terminando — DEFOnline</title>
</head>
<body style="font-family: 'Inter', system-ui, -apple-system, sans-serif; color: #0A2540; line-height: 1.55; background: #F6F9FC; margin: 0; padding: 24px;">
    <div style="max-width: 480px; margin: 0 auto; background: #FFFFFF; border: 1px solid #E3E8EE; border-radius: 16px; padding: 32px;">
        <p style="margin: 0 0 16px;">Olá, {{ $nome }}!</p>

        <p style="margin: 0 0 16px;">Seu desconto termina em {{ $dataFim }}. A partir daí o plano custa {{ $precoDepois }}.</p>

        @if ($semCartao)
            <p style="margin: 0 0 16px;">Sem um cartão cadastrado, as funções pagas serão bloqueadas depois dessa data. Cadastre o cartão em Meu plano para continuar.</p>
        @endif

        <p style="margin: 0 0 24px;">
            <a href="{{ $link }}" style="display: inline-block; background: #635BFF; color: #FFFFFF; padding: 12px 20px; border-radius: 10px; text-decoration: none; font-weight: 500;">
                {{ $semCartao ? 'Cadastrar cartão' : 'Ver meu plano' }}
            </a>
        </p>

        <p style="margin: 24px 0 0; color: #425466; font-size: 14px;">— Equipe DEFOnline</p>
    </div>
</body>
</html>
