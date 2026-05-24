<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Confirme seu email — DEFOnline</title>
</head>
{{--
  Email transacional — inline styles obrigatórios (clientes de email ignoram
  stylesheets externas e variáveis CSS). Os hex abaixo são a paleta v1 do
  design-system (Inter / Stripe-like). Mudou o design-system, atualizar aqui à
  mão. O teste arquitetural DesignTokensTest exclui este arquivo da regra
  "hex só em tokens.css".
--}}
<body style="font-family: 'Inter', system-ui, -apple-system, sans-serif; color: #0A2540; line-height: 1.55; background: #F6F9FC; margin: 0; padding: 24px;">
    <div style="max-width: 480px; margin: 0 auto; background: #FFFFFF; border: 1px solid #E3E8EE; border-radius: 16px; padding: 32px;">
        <p style="margin: 0 0 16px;">Olá, {{ $nome }}!</p>

        <p style="margin: 0 0 16px;">Para ativar sua conta no DEFOnline, confirme o seu email clicando no botão abaixo:</p>

        <p style="margin: 0 0 24px;">
            <a href="{{ $link }}" style="display: inline-block; background: #635BFF; color: #FFFFFF; padding: 12px 20px; border-radius: 10px; text-decoration: none; font-weight: 500;">
                Confirmar meu email
            </a>
        </p>

        <p style="margin: 0 0 12px; color: #425466; font-size: 14px;">Se o botão não funcionar, copie e cole este endereço no seu navegador:</p>
        <p style="word-break: break-all; margin: 0 0 24px; font-size: 14px;">
            <a href="{{ $link }}" style="color: #635BFF;">{{ $link }}</a>
        </p>

        <p style="margin: 0 0 16px; color: #425466; font-size: 14px;">
            <strong style="color: #0A2540;">Atenção:</strong> este link expira em 60 minutos. Se ele expirar, peça um novo no formulário de login.
        </p>

        <p style="margin: 0 0 16px; color: #425466; font-size: 14px;">Se você não criou esta conta, ignore este email.</p>

        <p style="margin: 24px 0 0; color: #425466; font-size: 14px;">— Equipe DEFOnline</p>
    </div>
</body>
</html>
