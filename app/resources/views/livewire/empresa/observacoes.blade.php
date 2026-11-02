<form wire:submit="salvar" novalidate class="flex flex-col gap-3">
    <x-label for="observacoes">
        Observações <span class="normal-case font-normal tracking-normal text-[color:var(--color-secondary)]">(suas anotações — não alteram os dados da Receita)</span>
    </x-label>
    <textarea id="observacoes" wire:model="observacoes" rows="4"
              maxlength="{{ \App\Livewire\Empresa\Cadastrar::OBSERVACOES_MAX }}"
              class="input min-h-24" dusk="empresa-show-observacoes">{{ $observacoes }}</textarea>
    @error('observacoes')
        <p class="text-[color:var(--color-destructive)] text-sm m-0" dusk="erro-observacoes">{{ $message }}</p>
    @enderror
    <div class="flex items-center gap-3">
        <x-button type="submit" variant="secondary" size="sm" dusk="empresa-show-salvar-observacoes">
            Salvar observações
        </x-button>
        @if ($salvo)
            <span class="text-sm text-[color:var(--color-secondary)]" role="status" dusk="empresa-show-observacoes-salvas">Observações salvas.</span>
        @endif
    </div>
</form>
