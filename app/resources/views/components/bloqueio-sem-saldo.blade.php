@props([
    'titulo',
    'causa',
    'rascunho' => false,
    'acoes' => [],
    'prefixo' => 'screen-bloqueio',
    'rodape' => true,
])

{{-- STORY-073 / spec STORY-070-bloqueio-sem-saldo: bloco no lugar do fluxo (nem modal nem toast).
     Sem "continuar assim mesmo" (DDR-001). As ações ficam fora do aviso. --}}
@php
    $personificando = \App\Support\Personificacao::ativa() !== null;
@endphp

<section class="max-w-[640px] flex flex-col gap-4" dusk="{{ $prefixo }}"
         x-data x-init="$nextTick(() => $refs.titulo.focus())">
    <x-page-header titulo-dusk="{{ $prefixo }}-titulo">{{ $titulo }}</x-page-header>

    <div class="rounded-md border border-[color:var(--color-destructive)]/40 bg-[color:var(--color-destructive)]/5 p-4"
         role="alert" dusk="{{ $prefixo }}-causa">
        <p class="m-0 text-[color:var(--color-primary)]">{{ $causa }}</p>
    </div>

    @if ($rascunho)
        <p class="m-0 text-sm text-[color:var(--color-primary)]" dusk="{{ $prefixo }}-rascunho-salvo">
            &#10003; Suas respostas estão salvas. Quando você resolver, continua de onde parou.
        </p>
    @endif

    @if ($acoes !== [])
        @if ($personificando)
            <p class="m-0 text-sm text-[color:var(--color-secondary)]">
                Você está logado como administrador: as ações abaixo ficam desativadas.
            </p>
        @endif
        <div class="flex flex-wrap gap-3">
            @foreach ($acoes as $i => $acao)
                <x-button :href="$acao['href']" :variant="$i === 0 ? 'primary' : 'secondary'" :disabled="$personificando"
                          dusk="{{ $prefixo }}-acao-{{ $i === 0 ? 'primaria' : 'secundaria' }}">{{ $acao['rotulo'] }}</x-button>
            @endforeach
        </div>
    @endif

    @if ($rodape)
        <p class="m-0 text-sm text-[color:var(--color-secondary)]">
            Seus diagnósticos anteriores continuam disponíveis.
            <x-link :href="route('home')" dusk="{{ $prefixo }}-ver-diagnosticos">Ver diagnósticos</x-link>
        </p>
    @endif
</section>
