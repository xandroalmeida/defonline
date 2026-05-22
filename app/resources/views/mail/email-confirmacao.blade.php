<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Confirme seu email — DEFOnline</title>
</head>
<body style="font-family: system-ui, -apple-system, sans-serif; color: #111827; line-height: 1.5;">
    <p>Olá, {{ $nome }}!</p>

    <p>Para ativar sua conta no DEFOnline, confirme o seu email clicando no link abaixo:</p>

    <p>
        <a href="{{ $link }}" style="display: inline-block; background: #2563eb; color: white; padding: 10px 18px; border-radius: 6px; text-decoration: none;">
            Confirmar meu email
        </a>
    </p>

    <p>Se o botão não funcionar, copie e cole este endereço no seu navegador:</p>
    <p style="word-break: break-all;"><a href="{{ $link }}">{{ $link }}</a></p>

    <p><strong>Atenção:</strong> este link expira em 60 minutos. Se ele expirar, peça um novo no formulário de login.</p>

    <p>Se você não criou esta conta, ignore este email.</p>

    <p>— Equipe DEFOnline</p>
</body>
</html>
