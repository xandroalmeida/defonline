{{-- STORY-066 CA-3 — sticky no rodapé: "voltar" fica à mão em qualquer rolagem. --}}
@php
    $personificacao = \App\Support\Personificacao::ativa();
@endphp
@if ($personificacao !== null)
    <div role="status" dusk="personificacao-aviso"
         class="print:hidden sticky bottom-0 z-40 flex flex-wrap items-center justify-between gap-3 px-4 sm:px-6 py-3 bg-[color:var(--color-primary)] text-[color:var(--color-on-primary)] text-sm">
        <p class="m-0 flex-1 min-w-[16rem]">
            Você está logado como <strong>{{ auth()->user()?->nome }}</strong> ({{ auth()->user()?->email }}).
            Tudo o que fizer aqui fica registrado para auditoria.
        </p>
        <form method="POST" action="{{ route('personificacao.encerrar') }}" class="m-0">
            @csrf
            <x-button type="submit" variant="secondary" size="sm" dusk="personificacao-voltar">Voltar ao admin</x-button>
        </form>
    </div>
@endif
