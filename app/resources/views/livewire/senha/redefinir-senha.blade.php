<div>
    <x-card class="!p-6 sm:!p-8">
        @if ($linkInvalido)
            <h1 class="text-[length:var(--text-h1)] font-medium leading-tight tracking-tight text-[color:var(--color-primary)] mb-2" dusk="senha-link-invalido">Link inválido ou vencido</h1>
            <p class="text-[color:var(--color-secondary)] text-sm mb-6 mt-0">Peça um novo link para redefinir a senha.</p>
            <x-button :href="route('senha.esqueci')" variant="primary" class="w-full" dusk="senha-pedir-novo-link">Pedir novo link</x-button>
        @else
            <h1 class="text-[length:var(--text-h1)] font-medium leading-tight tracking-tight text-[color:var(--color-primary)] mb-2" dusk="senha-redefinir-titulo">Criar nova senha</h1>
            <p class="text-[color:var(--color-secondary)] text-sm mb-6 mt-0">Ao salvar, as sessões abertas da sua conta são encerradas.</p>

            <form wire:submit="salvar" class="flex flex-col gap-4" novalidate>
                <div>
                    <x-label for="nova-senha">Nova senha <span class="normal-case font-normal tracking-normal text-[color:var(--color-secondary)]">(mín. 8 caracteres, com letras e números)</span></x-label>
                    <x-input type="password" id="nova-senha" wire:model="novaSenha" autocomplete="new-password" aria-describedby="nova-senha-erro" dusk="senha-nova"/>
                    @error('novaSenha')<p id="nova-senha-erro" class="text-[color:var(--color-destructive)] text-sm mt-1 mb-0" dusk="senha-nova-erro">{{ $message }}</p>@enderror
                </div>
                <div>
                    <x-label for="nova-senha-confirmacao">Confirme a nova senha</x-label>
                    <x-input type="password" id="nova-senha-confirmacao" wire:model="novaSenha_confirmation" autocomplete="new-password" dusk="senha-nova-confirmacao"/>
                </div>
                <x-button type="submit" variant="primary" class="w-full mt-2" wire:loading.attr="disabled" wire:target="salvar" dusk="senha-salvar">Salvar nova senha</x-button>
            </form>
        @endif
    </x-card>
</div>
