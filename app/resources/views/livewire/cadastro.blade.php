<div class="container">
    <h1>Criar conta</h1>

    <form wire:submit="submit" novalidate>
        <label for="cpf">CPF</label>
        <input type="text" id="cpf" wire:model="cpf"
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
               dusk="cadastro-cpf">
        @error('cpf') <p class="erro" dusk="erro-cpf">{{ $message }}</p> @enderror

        <label for="nome">Nome completo</label>
        <input type="text" id="nome" wire:model="nome" autocomplete="name" dusk="cadastro-nome">
        @error('nome') <p class="erro">{{ $message }}</p> @enderror

        <label for="email">Email</label>
        <input type="email" id="email" wire:model="email" autocomplete="email" dusk="cadastro-email">
        @error('email') <p class="erro" dusk="erro-email">{{ $message }}</p> @enderror

        <label for="senha">Senha (mínimo 8 caracteres, com letras e números)</label>
        <input type="password" id="senha" wire:model="senha" autocomplete="new-password" dusk="cadastro-senha">
        @error('senha') <p class="erro">{{ $message }}</p> @enderror

        <label for="senha_confirmation">Confirme a senha</label>
        <input type="password" id="senha_confirmation" wire:model="senha_confirmation" autocomplete="new-password" dusk="cadastro-senha-confirmation">

        <label for="telefone">Telefone WhatsApp (DDD + número)</label>
        <input type="text" id="telefone" wire:model="telefone"
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
               dusk="cadastro-telefone">
        @error('telefone') <p class="erro">{{ $message }}</p> @enderror

        <button type="submit" class="primary" dusk="cadastro-submit">Criar conta</button>
    </form>

    <p style="margin-top: 1.5rem; text-align: center;">
        Já tem conta? <a href="/login" wire:navigate>Entrar</a>
    </p>
</div>
