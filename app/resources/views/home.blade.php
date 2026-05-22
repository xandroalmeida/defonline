@extends('layouts.app')

@section('conteudo')
<nav class="topo">
    <strong>DEFOnline</strong>
    <form method="POST" action="/logout" style="margin: 0;">
        @csrf
        <button type="submit" dusk="logout" style="background: none; border: none; color: #2563eb; cursor: pointer; font: inherit;">
            Sair
        </button>
    </form>
</nav>

<div class="container">
    <h1 dusk="saudacao">Olá, {{ $usuario->primeiroNome() }}</h1>
    <p>Conta criada. Em breve você verá aqui suas empresas analisadas.</p>
</div>
@endsection
