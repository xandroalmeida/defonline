@extends('layouts.app')

@section('conteudo')
<div class="container">
    <h1 dusk="email-confirmado-titulo">Email confirmado</h1>
    <div class="sucesso" dusk="email-confirmado-msg">
        Email confirmado. Você já pode fazer login.
    </div>
    <p style="margin-top: 1.5rem; text-align: center;">
        <a href="/login" wire:navigate>Ir para o login</a>
    </p>
</div>
@endsection
