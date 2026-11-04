{{-- Botão de ação destrutiva: pede confirmação em `<x-confirmacao>` e só então chama `$wire.{acao}`. --}}
@props(['titulo', 'mensagem', 'acao'])
<x-button type="button" variant="destructive" size="sm"
          x-on:click="$dispatch('pedir-confirmacao', {
              titulo: {{ \Illuminate\Support\Js::from($titulo) }},
              mensagem: {{ \Illuminate\Support\Js::from($mensagem) }},
              rotulo: {{ \Illuminate\Support\Js::from(trim((string) $slot)) }},
              acao: () => $wire.{{ $acao }},
          })"
          {{ $attributes }}>{{ $slot }}</x-button>
