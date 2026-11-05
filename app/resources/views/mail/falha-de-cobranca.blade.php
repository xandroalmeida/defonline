<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Não conseguimos cobrar a renovação — DEFOnline</title>
</head>
{{-- Email transacional — inline styles, mesma paleta do email-confirmacao (ver o comentário dele). --}}
<body style="font-family: 'Inter', system-ui, -apple-system, sans-serif; color: #0A2540; line-height: 1.55; background: #F6F9FC; margin: 0; padding: 24px;">
    <div style="max-width: 480px; margin: 0 auto; background: #FFFFFF; border: 1px solid #E3E8EE; border-radius: 16px; padding: 32px;">
        <p style="margin: 0 0 16px;">Olá, {{ $nome }}!</p>

        <p style="margin: 0 0 16px;">Não conseguimos cobrar a renovação do seu plano {{ $plano }} ({{ $valor }}). Seu plano está em atraso e as análises estão pausadas.</p>
        <p style="margin: 0 0 16px;">Atualize o cartão para manter o acesso. Vamos tentar de novo em {{ $proximaTentativa }}; a última tentativa será em {{ $ultimaTentativa }}. Depois dela, o plano é encerrado.</p>

        <p style="margin: 0 0 24px;">
            <a href="{{ $link }}" style="display: inline-block; background: #635BFF; color: #FFFFFF; padding: 12px 20px; border-radius: 10px; text-decoration: none; font-weight: 500;">
                Atualizar o cartão
            </a>
        </p>

        <p style="margin: 0 0 16px; color: #425466; font-size: 14px;">
            Seus diagnósticos já feitos continuam disponíveis. Se você já resolveu, ignore este aviso.
        </p>
        <p style="margin: 24px 0 0; color: #425466; font-size: 14px;">— Equipe DEFOnline</p>
    </div>
</body>
</html>
