<div class="container">
    <h1>Entrar</h1>

    @if (session('cadastro_sucesso'))
        <div class="sucesso" dusk="cadastro-sucesso">{{ session('cadastro_sucesso') }}</div>
    @endif

    <form wire:submit="submit" novalidate>
        <label for="email">Email</label>
        <input type="email" id="email" wire:model.defer="email" autocomplete="email" dusk="login-email">
        @error('email') <p class="erro" dusk="erro-login">{{ $message }}</p> @enderror

        <label for="senha">Senha</label>
        <input type="password" id="senha" wire:model.defer="senha" autocomplete="current-password" dusk="login-senha">
        @error('senha') <p class="erro">{{ $message }}</p> @enderror

        <button type="submit" class="primary" dusk="login-submit">Entrar</button>
    </form>

    <p style="margin-top: 1rem; text-align: center;">
        <a href="{{ route('email.confirmar-erro') }}" wire:navigate dusk="login-reenviar-email">Reenviar email de confirmação</a>
    </p>

    <p style="margin-top: 1.5rem; text-align: center;">
        Ainda não tem conta? <a href="/cadastro" wire:navigate>Criar conta</a>
    </p>
</div>
