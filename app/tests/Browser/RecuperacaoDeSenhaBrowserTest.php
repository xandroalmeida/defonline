<?php

declare(strict_types=1);

namespace Tests\Browser;

use App\Models\Usuario;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Password;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/** STORY-100 — do login à nova senha, e entrada com ela. */
class RecuperacaoDeSenhaBrowserTest extends DuskTestCase
{
    use DatabaseMigrations;

    public function test_usuario_pede_o_link_cria_nova_senha_e_entra(): void
    {
        $usuario = Usuario::factory()->create(['senha_hash' => 'SenhaAntiga1', 'email_confirmed_at' => now()]);

        $this->browse(function (Browser $browser) use ($usuario) {
            $browser->resize(390, 900)->visit('/login')
                ->waitFor('@login-esqueci-senha')
                ->click('@login-esqueci-senha')
                ->waitFor('@senha-esqueci-email')
                ->type('@senha-esqueci-email', $usuario->email)
                ->click('@senha-esqueci-enviar')
                ->waitFor('@senha-esqueci-enviado');

            $token = Password::broker()->createToken($usuario);

            $browser->visit(route('senha.redefinir', ['usuario' => $usuario->id, 'token' => $token], false))
                ->waitFor('@senha-nova')
                ->type('@senha-nova', 'SenhaNova456')
                ->type('@senha-nova-confirmacao', 'SenhaNova456')
                ->click('@senha-salvar')
                ->waitFor('@senha-redefinida')
                ->type('@login-email', $usuario->email)
                ->type('@login-senha', 'SenhaNova456')
                ->click('@login-submit')
                ->waitForLocation('/home');
        });
    }
}
