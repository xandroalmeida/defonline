<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Seu plano anual termina em {{ $dataFim }} — DEFOnline</title>
</head>
{{-- Email transacional — inline styles, mesma paleta do email-confirmacao (ver o comentário dele). --}}
<body style="font-family: 'Inter', system-ui, -apple-system, sans-serif; color: #0A2540; line-height: 1.55; background: #F6F9FC; margin: 0; padding: 24px;">
    <div style="max-width: 480px; margin: 0 auto; background: #FFFFFF; border: 1px solid #E3E8EE; border-radius: 16px; padding: 32px;">
        <p style="margin: 0 0 16px;">Olá, {{ $nome }}!</p>

        <p style="margin: 0 0 16px;">Seu plano {{ $plano }} (anual) termina em <strong>{{ $dataFim }}</strong>. Como você paga com Pix, ele <strong>não renova sozinho</strong>.</p>

        <p style="margin: 0 0 16px;">Para continuar, pague a renovação de <strong>{{ $valor }}</strong> com um novo QR Code. O novo ciclo vai até {{ $novoFim }} e começa quando o atual termina, sem perder nenhum dia.</p>

        <p style="margin: 0 0 24px;">
            <a href="{{ $link }}" style="display: inline-block; background: #635BFF; color: #FFFFFF; padding: 12px 20px; border-radius: 10px; text-decoration: none; font-weight: 500;">
                Pagar renovação
            </a>
        </p>

        <p style="margin: 0 0 16px; color: #425466; font-size: 14px;">
            Se você não pagar, as funções pagas são bloqueadas em {{ $dataFim }} e nada é cobrado. Os diagnósticos que você já fez continuam disponíveis.
        </p>

        <p style="margin: 24px 0 0; color: #425466; font-size: 14px;">— Equipe DEFOnline</p>
    </div>
</body>
</html>
