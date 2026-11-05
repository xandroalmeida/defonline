{{-- PDR-019 — e-mail e telefone/WhatsApp da empresa, opcionais. --}}
@props(['prefixoDusk' => 'empresa'])

<div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
    <div>
        <x-label for="email">
            E-mail <span class="normal-case font-normal tracking-normal text-[color:var(--color-secondary)]">(opcional)</span>
        </x-label>
        <x-input type="email" id="email" wire:model="email" inputmode="email" autocomplete="off"
                 maxlength="255" dusk="{{ $prefixoDusk }}-email"/>
        @error('email')
            <p class="text-[color:var(--color-destructive)] text-sm mt-1 mb-0" dusk="erro-email">{{ $message }}</p>
        @enderror
    </div>
    <div>
        <x-label for="telefone">
            Telefone / WhatsApp <span class="normal-case font-normal tracking-normal text-[color:var(--color-secondary)]">(opcional)</span>
        </x-label>
        <x-input type="text" id="telefone" wire:model="telefone"
                 inputmode="tel" autocomplete="off"
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
                 dusk="{{ $prefixoDusk }}-telefone"/>
        @error('telefone')
            <p class="text-[color:var(--color-destructive)] text-sm mt-1 mb-0" dusk="erro-telefone">{{ $message }}</p>
        @enderror
    </div>
</div>
