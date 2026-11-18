<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Recebemos sua demanda de captação — DEFOnline</title>
</head>
{{-- Email transacional — inline styles, mesma paleta do email-confirmacao (ver o comentário dele). --}}
<body style="font-family: 'Inter', system-ui, -apple-system, sans-serif; color: #0A2540; line-height: 1.55; background: #F6F9FC; margin: 0; padding: 24px;">
    <div style="max-width: 480px; margin: 0 auto; background: #FFFFFF; border: 1px solid #E3E8EE; border-radius: 16px; padding: 32px;">
        <p style="margin: 0 0 16px;">Olá, {{ $nome }}!</p>

        <p style="margin: 0 0 16px;">Recebemos a demanda de captação de recursos de {{ $empresa }}, informada no diagnóstico.</p>

        <p style="margin: 0 0 16px;">Nossa equipe retornará com a análise em até {{ $diasUteis }} dias úteis pelo e-mail ou WhatsApp cadastrado.</p>

        <p style="margin: 24px 0 0; color: #425466; font-size: 14px;">— Equipe DEFOnline</p>
    </div>
</body>
</html>
