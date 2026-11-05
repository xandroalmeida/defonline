{{--
    <x-drm.aviso-inline> — três severidades, e só três (DDR-001 item 5):
      info     contexto, sem ação obrigatória.
      alerta   pede confirmação e oferece uma saída para frente.
      bloqueio impede o avanço; a AUSÊNCIA do botão de seguir é o que o distingue do alerta.
    `bloqueio` usa role=alert/aria-live=assertive; `alerta` e `info`, aria-live=polite.
    Texto de título sempre em --color-primary (contraste AA, spec §6).
--}}
@props(['variant' => 'info', 'titulo' => null])
@php
    $bloqueio = $variant === 'bloqueio';
    $moldura = match ($variant) {
        'bloqueio' => 'border-[color:var(--color-destructive)] bg-[color:var(--color-surface)]',
        'alerta' => 'border-[color:var(--color-farol-amarelo)] bg-[color:var(--color-surface)]',
        default => 'border-[color:var(--color-border)] bg-[color:var(--color-neutral)]',
    };
@endphp
<div {{ $attributes->merge(['class' => "rounded-md border px-4 py-3 mb-4 text-sm text-[color:var(--color-primary)] {$moldura}"]) }}
     @if ($bloqueio) role="alert" aria-live="assertive" @else aria-live="polite" @endif>
    @if ($titulo)
        <p class="m-0 mb-1 font-semibold text-[color:var(--color-primary)]">{{ $titulo }}</p>
    @endif
    {{ $slot }}
</div>
