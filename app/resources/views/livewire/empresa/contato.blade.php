<form wire:submit="salvar" novalidate class="flex flex-col gap-3">
    <x-empresa.campos-contato prefixo-dusk="empresa-show"/>
    <div class="flex items-center gap-3">
        <x-button type="submit" variant="secondary" size="sm" dusk="empresa-show-salvar-contato">
            Salvar contato
        </x-button>
        @if ($salvo)
            <span class="text-sm text-[color:var(--color-secondary)]" role="status" dusk="empresa-show-contato-salvo">Contato salvo.</span>
        @endif
    </div>
</form>
