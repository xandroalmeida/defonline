<div dusk="renovacao-prazo">
    <h2 class="text-lg font-medium m-0 mb-1 text-[color:var(--color-primary)]">Aviso para renovar o diagnóstico</h2>
    <p class="m-0 mb-3 text-sm text-[color:var(--color-secondary)]">Avisamos aqui e por e-mail quando esse tempo passar desde o último diagnóstico.</p>
    <x-label for="renovacao-meses">Avisar depois de</x-label>
    <x-select id="renovacao-meses" wire:model.live="meses" :disabled="$personificando" aria-describedby="renovacao-meses-erro" dusk="renovacao-meses" class="max-w-[240px]">
        @foreach ($prazos as $prazo)
            <option value="{{ $prazo }}">{{ $prazo }} meses</option>
        @endforeach
    </x-select>
    <div aria-live="polite">
        @error('meses')<p id="renovacao-meses-erro" class="text-[color:var(--color-destructive)] text-sm m-0 mt-1">{{ $message }}</p>@enderror
        @if ($salvo)<p class="m-0 mt-1 text-sm text-[color:var(--color-secondary)]" dusk="renovacao-meses-salvo">Prazo salvo.</p>@endif
    </div>
</div>
