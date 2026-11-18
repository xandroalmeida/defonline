{{-- STORY-104 — diagnósticos na ficha do usuário e da empresa. --}}
@php $veCaptacao = auth()->user()?->podeUsarBackoffice(\App\Support\PermissaoAdmin::GerenciarCaptacao); @endphp
@if ($diagnosticos->isEmpty())
    <x-card>Nenhum diagnóstico.</x-card>
@else
    <div class="card p-0 relative overflow-x-auto">
        <table class="w-full text-sm text-left" dusk="ficha-diagnosticos">
            <thead class="text-[color:var(--color-secondary)]">
                <tr>
                    <th scope="col" class="px-2 py-2">Gerado em</th>
                    @if ($mostrarEmpresa)
                        <th scope="col" class="px-2 py-2">Empresa</th>
                    @endif
                    <th scope="col" class="px-2 py-2">Captação</th>
                    <th scope="col" class="px-2 py-2">Origem</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($diagnosticos as $d)
                    <tr class="border-t border-[color:var(--color-border)]" wire:key="d-{{ $d->id }}" dusk="ficha-diagnostico-{{ $d->id }}">
                        <td class="px-2 py-2 whitespace-nowrap">{{ \App\Support\DataExibicao::dataHora($d->gerado_em) }}</td>
                        @if ($mostrarEmpresa)
                            <td class="px-2 py-2">{{ $d->empresa?->nomeExibicao() ?? '—' }}</td>
                        @endif
                        <td class="px-2 py-2">
                            @if (isset($demandas[$d->id]) && $veCaptacao)
                                <a class="link" href="{{ route('backoffice.captacao', ['demanda' => $demandas[$d->id]]) }}">Ver demanda</a>
                            @elseif (isset($demandas[$d->id]))
                                Sim
                            @else
                                —
                            @endif
                        </td>
                        <td class="px-2 py-2">{{ $d->personificado_por_id ? 'Via admin' : 'Cliente' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
