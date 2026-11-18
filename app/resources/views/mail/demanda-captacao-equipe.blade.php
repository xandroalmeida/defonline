<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Nova demanda de captação — DEFOnline</title>
</head>
{{-- Email interno para a equipe — inline styles, mesma paleta do email-confirmacao. --}}
<body style="font-family: 'Inter', system-ui, -apple-system, sans-serif; color: #0A2540; line-height: 1.55; background: #F6F9FC; margin: 0; padding: 24px;">
    <div style="max-width: 560px; margin: 0 auto; background: #FFFFFF; border: 1px solid #E3E8EE; border-radius: 16px; padding: 32px;">
        <p style="margin: 0 0 16px;"><strong>Nova demanda de captação.</strong> Retorno ao cliente até <strong>{{ $prazo }}</strong> (5 dias úteis).</p>

        <p style="margin: 0 0 4px;"><strong>{{ $empresa }}</strong> — {{ $documento }}</p>
        @if ($empresaEmail || $empresaTelefone)
            <p style="margin: 0 0 16px; color: #425466;">Contato da empresa: {{ collect([$empresaEmail, $empresaTelefone])->filter()->join(' · ') }}</p>
        @endif

        <p style="margin: 0 0 16px;">Usuário: {{ $usuarioNome }} · {{ $usuarioEmail }}@if ($usuarioTelefone) · {{ $usuarioTelefone }}@endif</p>

        <table style="border-collapse: collapse; margin: 0 0 16px; font-size: 14px;">
            <tr><td style="padding: 2px 12px 2px 0; color: #425466;">Valor que precisa captar</td><td>{{ $valorCaptar ?? '—' }}</td></tr>
            <tr><td style="padding: 2px 12px 2px 0; color: #425466;">Endividamento total no mercado</td><td>{{ $endividamento ?? '—' }}</td></tr>
            <tr><td style="padding: 2px 12px 2px 0; color: #425466;">Venda mensal com cartão / duplicatas</td><td>{{ $vendaCartao ?? '—' }}</td></tr>
            @if ($socios !== [])
                <tr><td style="padding: 2px 12px 2px 0; color: #425466;">Sócios</td><td>{{ implode(', ', $socios) }}</td></tr>
            @endif
        </table>

        <p style="margin: 0 0 16px; color: #425466; font-size: 14px;">O cliente autorizou o contato pelo e-mail ou WhatsApp cadastrado.</p>

        <p style="margin: 0;"><a href="{{ $link }}" style="color: #635BFF;">Ver as demandas no backoffice</a></p>
    </div>
</body>
</html>
