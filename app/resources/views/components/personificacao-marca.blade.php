{{-- STORY-066 CA-6 — avisa, na própria tela, que o registro foi gravado por um admin. --}}
@props(['registro'])
@if ($registro->personificadoPor)
    <p {{ $attributes->merge(['class' => 'text-sm text-[color:var(--color-secondary)]']) }} dusk="personificacao-marca">
        Última alteração feita por {{ $registro->personificadoPor->nome }}, da equipe DEFOnline, com acesso à conta do usuário.
    </p>
@endif
