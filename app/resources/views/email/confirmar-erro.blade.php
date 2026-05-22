@extends('layouts.app')

@section('conteudo')
<div class="container">
    <h1 dusk="email-erro-titulo">Não foi possível confirmar</h1>

    @php($motivo = session('email_confirmar_erro_motivo', 'invalido'))

    <p dusk="email-erro-msg">
        @switch($motivo)
            @case('ja_confirmado')
                Este email já foi confirmado anteriormente. Faça login normalmente.
                @break
            @case('expirado')
                O link de confirmação expirou. Solicite um novo abaixo.
                @break
            @default
                O link de confirmação é inválido ou expirou. Solicite um novo abaixo.
        @endswitch
    </p>

    @if (session('email_reenvio_aviso'))
        <div class="sucesso" dusk="email-reenvio-aviso">{{ session('email_reenvio_aviso') }}</div>
    @endif

    <form method="POST" action="{{ route('email.reenviar') }}" novalidate>
        @csrf
        <label for="email">Email cadastrado</label>
        <input type="email" id="email" name="email" autocomplete="email" required dusk="email-reenvio-email">
        <button type="submit" class="primary" dusk="email-reenvio-submit">Reenviar email</button>
    </form>

    <p style="margin-top: 1.5rem; text-align: center;">
        <a href="/login" wire:navigate>Voltar ao login</a>
    </p>
</div>
@endsection
