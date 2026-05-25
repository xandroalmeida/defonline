<x-layouts.app :title="'Diagnóstico ' . $diagnostico->id">
    <x-page-header :title="'Diagnóstico calculado'"
                   :subtitle="'STORY-029 substituirá este placeholder pelo relatório minimalista de 7 indicadores.'"/>

    <x-card>
        <dl class="grid grid-cols-1 sm:grid-cols-2 gap-y-3 gap-x-6 text-sm">
            <div>
                <dt class="text-[color:var(--color-secondary)] uppercase tracking-wider text-xs">ID</dt>
                <dd class="text-[color:var(--color-primary)] font-mono" dusk="diagnostico-stub-id">{{ $diagnostico->id }}</dd>
            </div>
            <div>
                <dt class="text-[color:var(--color-secondary)] uppercase tracking-wider text-xs">Empresa</dt>
                <dd class="text-[color:var(--color-primary)]">{{ $diagnostico->empresa->nome_fantasia ?: $diagnostico->empresa->razao_social }}</dd>
            </div>
            <div>
                <dt class="text-[color:var(--color-secondary)] uppercase tracking-wider text-xs">Versão do motor</dt>
                <dd class="text-[color:var(--color-primary)] font-mono" dusk="diagnostico-stub-motor">{{ $diagnostico->motor_version }}</dd>
            </div>
            <div>
                <dt class="text-[color:var(--color-secondary)] uppercase tracking-wider text-xs">Versão da matriz</dt>
                <dd class="text-[color:var(--color-primary)] font-mono">{{ $diagnostico->matrix_version }}</dd>
            </div>
            <div>
                <dt class="text-[color:var(--color-secondary)] uppercase tracking-wider text-xs">Setor</dt>
                <dd class="text-[color:var(--color-primary)]">{{ ucfirst($diagnostico->setor) }}</dd>
            </div>
            <div>
                <dt class="text-[color:var(--color-secondary)] uppercase tracking-wider text-xs">Gerado em</dt>
                <dd class="text-[color:var(--color-primary)]">{{ $diagnostico->gerado_em->format('d/m/Y H:i') }}</dd>
            </div>
        </dl>

        <div class="mt-6 pt-4 border-t border-[color:var(--color-border)]">
            <p class="text-[color:var(--color-secondary)] text-sm m-0">
                {{ count((array) $diagnostico->indicadores_calculados) }} indicadores calculados.
                Relatório completo entregue pela STORY-029.
            </p>
        </div>
    </x-card>

    <div class="mt-4 flex gap-2">
        <x-button :href="route('home')" variant="secondary" size="sm">
            Voltar para Minhas Empresas
        </x-button>
    </div>
</x-layouts.app>
