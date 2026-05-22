<div class="container">
    <h1>Criar conta</h1>

    <form wire:submit="submit" novalidate>
        <label for="cpf">CPF</label>
        <input type="text" id="cpf" wire:model.defer="cpf" inputmode="numeric" autocomplete="off" dusk="cadastro-cpf">
        @error('cpf') <p class="erro" dusk="erro-cpf">{{ $message }}</p> @enderror

        <label for="nome">Nome completo</label>
        <input type="text" id="nome" wire:model.defer="nome" autocomplete="name" dusk="cadastro-nome">
        @error('nome') <p class="erro">{{ $message }}</p> @enderror

        <label for="email">Email</label>
        <input type="email" id="email" wire:model.defer="email" autocomplete="email" dusk="cadastro-email">
        @error('email') <p class="erro" dusk="erro-email">{{ $message }}</p> @enderror

        <label for="senha">Senha (mínimo 8 caracteres, com letras e números)</label>
        <input type="password" id="senha" wire:model.defer="senha" autocomplete="new-password" dusk="cadastro-senha">
        @error('senha') <p class="erro">{{ $message }}</p> @enderror

        <label for="senha_confirmation">Confirme a senha</label>
        <input type="password" id="senha_confirmation" wire:model.defer="senha_confirmation" autocomplete="new-password" dusk="cadastro-senha-confirmation">

        <label for="telefone">Telefone WhatsApp (DDD + número)</label>
        <input type="text" id="telefone" wire:model.defer="telefone" inputmode="tel" autocomplete="tel" dusk="cadastro-telefone">
        @error('telefone') <p class="erro">{{ $message }}</p> @enderror

        <button type="submit" class="primary" dusk="cadastro-submit">Criar conta</button>
    </form>

    <p style="margin-top: 1.5rem; text-align: center;">
        Já tem conta? <a href="/login" wire:navigate>Entrar</a>
    </p>
</div>
