<div>
    <x-card class="!p-6 sm:!p-8">
        <h1 class="text-[length:var(--text-h1)] font-medium leading-tight tracking-tight text-[color:var(--color-primary)] mb-2"
            dusk="cadastro-titulo">
            Criar conta
        </h1>
        <p class="text-[color:var(--color-secondary)] text-sm mb-6 mt-0">
            Preencha seus dados para começar a usar o DEFOnline.
        </p>

        <form wire:submit="submit" novalidate class="flex flex-col gap-4">
            <div>
                <x-label for="cpf">CPF</x-label>
                <x-input type="text" id="cpf" wire:model="cpf"
                         inputmode="numeric" autocomplete="off"
                         maxlength="14" placeholder="000.000.000-00"
                         x-data
                         x-on:input="
                             const d = $el.value.replace(/\D/g, '').slice(0, 11);
                             let m = d;
                             if (d.length > 9)      m = d.slice(0,3)+'.'+d.slice(3,6)+'.'+d.slice(6,9)+'-'+d.slice(9);
                             else if (d.length > 6) m = d.slice(0,3)+'.'+d.slice(3,6)+'.'+d.slice(6);
                             else if (d.length > 3) m = d.slice(0,3)+'.'+d.slice(3);
                             $el.value = m;
                         "
                         dusk="cadastro-cpf"/>
                @error('cpf')
                    <p class="text-[color:var(--color-destructive)] text-sm mt-1 mb-0" dusk="erro-cpf">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <x-label for="nome">Nome completo</x-label>
                <x-input type="text" id="nome" wire:model="nome" autocomplete="name" dusk="cadastro-nome"/>
                @error('nome')
                    <p class="text-[color:var(--color-destructive)] text-sm mt-1 mb-0">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <x-label for="email">Email</x-label>
                <x-input type="email" id="email" wire:model="email" autocomplete="email" dusk="cadastro-email"/>
                @error('email')
                    <p class="text-[color:var(--color-destructive)] text-sm mt-1 mb-0" dusk="erro-email">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <x-label for="senha">Senha <span class="normal-case font-normal tracking-normal text-[color:var(--color-secondary)]">(mín. 8 caracteres, com letras e números)</span></x-label>
                <x-input type="password" id="senha" wire:model="senha" autocomplete="new-password" dusk="cadastro-senha"/>
                @error('senha')
                    <p class="text-[color:var(--color-destructive)] text-sm mt-1 mb-0">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <x-label for="senha_confirmation">Confirme a senha</x-label>
                <x-input type="password" id="senha_confirmation" wire:model="senha_confirmation"
                         autocomplete="new-password" dusk="cadastro-senha-confirmation"/>
            </div>

            <div>
                <x-label for="telefone">Telefone WhatsApp <span class="normal-case font-normal tracking-normal text-[color:var(--color-secondary)]">(DDD + número)</span></x-label>
                <x-input type="text" id="telefone" wire:model="telefone"
                         inputmode="tel" autocomplete="tel"
                         maxlength="16" placeholder="(11) 98888-7777"
                         x-data
                         x-on:input="
                             const d = $el.value.replace(/\D/g, '').slice(0, 11);
                             let m = d;
                             if (d.length > 10)      m = '('+d.slice(0,2)+') '+d.slice(2,7)+'-'+d.slice(7);
                             else if (d.length > 6)  m = '('+d.slice(0,2)+') '+d.slice(2,6)+'-'+d.slice(6);
                             else if (d.length > 2)  m = '('+d.slice(0,2)+') '+d.slice(2);
                             else if (d.length > 0)  m = '('+d;
                             $el.value = m;
                         "
                         dusk="cadastro-telefone"/>
                @error('telefone')
                    <p class="text-[color:var(--color-destructive)] text-sm mt-1 mb-0">{{ $message }}</p>
                @enderror
            </div>

            <fieldset class="rounded-md border border-[color:var(--color-border)] p-4 mt-2 flex flex-col gap-3">
                <legend class="px-1 text-xs font-semibold uppercase tracking-wider text-[color:var(--color-secondary)]">
                    Termos e consentimentos
                </legend>

                <label class="flex gap-2.5 items-start text-sm font-normal cursor-pointer">
                    <input type="checkbox" wire:model="aceite_termo_adesao"
                           dusk="cadastro-aceite-termo-adesao"
                           class="mt-1 accent-[color:var(--color-tertiary)]">
                    <span class="text-[color:var(--color-primary)]">
                        Li e aceito o
                        <a href="{{ route('termos.termo-adesao') }}" target="_blank" rel="noopener"
                           class="link" dusk="cadastro-link-termo-adesao">Termo de Adesão</a>
                        <small class="text-[color:var(--color-secondary)]">(abre em nova aba)</small>
                    </span>
                </label>
                @error('aceite_termo_adesao')
                    <p class="text-[color:var(--color-destructive)] text-sm m-0" dusk="erro-aceite-termo-adesao">{{ $message }}</p>
                @enderror

                <label class="flex gap-2.5 items-start text-sm font-normal cursor-pointer">
                    <input type="checkbox" wire:model="aceite_lgpd" dusk="cadastro-aceite-lgpd"
                           class="mt-1 accent-[color:var(--color-tertiary)]">
                    <span class="text-[color:var(--color-primary)]">
                        Li e aceito a
                        <a href="{{ route('termos.politica-privacidade') }}" target="_blank" rel="noopener"
                           class="link" dusk="cadastro-link-lgpd">Política de Privacidade e LGPD</a>
                        <small class="text-[color:var(--color-secondary)]">(abre em nova aba)</small>
                    </span>
                </label>
                @error('aceite_lgpd')
                    <p class="text-[color:var(--color-destructive)] text-sm m-0" dusk="erro-aceite-lgpd">{{ $message }}</p>
                @enderror

                <label class="flex gap-2.5 items-start text-sm font-normal cursor-pointer">
                    <input type="checkbox" wire:model="aceite_marketing" dusk="cadastro-aceite-marketing"
                           class="mt-1 accent-[color:var(--color-tertiary)]">
                    <span class="text-[color:var(--color-primary)]">
                        Quero receber comunicações de marketing por email/WhatsApp
                        <small class="text-[color:var(--color-secondary)]">(opcional)</small>
                    </span>
                </label>
            </fieldset>

            <x-button type="submit" variant="primary" class="w-full mt-2" dusk="cadastro-submit">
                Criar conta
            </x-button>
        </form>

        <p class="mt-6 text-sm text-center text-[color:var(--color-secondary)] m-0">
            Já tem conta?
            <a href="{{ route('login') }}" wire:navigate class="link">Entrar</a>
        </p>
    </x-card>
</div>
