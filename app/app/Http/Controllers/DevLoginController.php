<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Usuario;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Login rápido para testes locais: lista as contas do banco local e entra em qualquer uma com um
 * clique, sem senha. Só existe com `APP_ENV=local`; em qualquer outro ambiente responde 404 como se
 * não existisse (a rota também só é registrada em local).
 */
final class DevLoginController extends Controller
{
    public const LIMITE = 100;

    public function index(Request $request): View
    {
        self::exigirLocal();

        $busca = trim((string) $request->query('q', ''));
        $padrao = '%'.addcslashes($busca, '%_\\').'%';

        $usuarios = Usuario::query()
            ->when($busca !== '', fn ($q) => $q->where(fn ($w) => $w->where('nome', 'ilike', $padrao)->orWhere('email', 'ilike', $padrao)))
            ->orderByDesc('created_at')
            ->limit(self::LIMITE)
            ->get();

        return view('dev.entrar', ['usuarios' => $usuarios, 'busca' => $busca, 'atual' => $request->user()]);
    }

    public function entrar(Request $request, Usuario $usuario): RedirectResponse
    {
        self::exigirLocal();

        // Sessão nova: não herda personificação nem estado da conta anterior.
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        Auth::login($usuario);
        $request->session()->regenerate();

        return redirect('/home');
    }

    public static function disponivel(): bool
    {
        return app()->environment('local');
    }

    private static function exigirLocal(): void
    {
        abort_unless(self::disponivel(), 404);
    }
}
