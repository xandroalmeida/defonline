<?php

declare(strict_types=1);

use App\Http\Controllers\CnaeController;
use App\Http\Controllers\DiagnosticoController;
use App\Http\Controllers\DrmMesController;
use App\Http\Controllers\EmailConfirmacaoController;
use App\Http\Controllers\EmpresaController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\PersonificacaoController;
use App\Livewire\Backoffice\Administradores as BackofficeAdministradores;
use App\Livewire\Backoffice\Cupons as BackofficeCupons;
use App\Livewire\Backoffice\Glossario as BackofficeGlossario;
use App\Livewire\Backoffice\Planos as BackofficePlanos;
use App\Livewire\Backoffice\Recomendacoes as BackofficeRecomendacoes;
use App\Livewire\Backoffice\Usuarios as BackofficeUsuarios;
use App\Livewire\Cadastro;
use App\Livewire\Diagnostico\Quiz as QuizDiagnostico;
use App\Livewire\Diagnostico\SelecionarEmpresa as SelecionarEmpresaDiagnostico;
use App\Livewire\Drm\Lancamento as LancamentoDrm;
use App\Livewire\Empresa\Cadastrar as CadastrarEmpresa;
use App\Livewire\Home\MinhasEmpresas;
use App\Livewire\Login;
use Illuminate\Support\Facades\Route;

// STORY-024 — landing pública simples (substitui a página de debug welcome + hello-world).
Route::view('/', 'landing')->name('landing');

Route::get('/health', [HealthController::class, 'health'])->name('health');
Route::get('/ready', [HealthController::class, 'ready'])->name('ready');

Route::get('/cadastro', Cadastro::class)->name('cadastro');
Route::get('/login', Login::class)->middleware('throttle:login')->name('login');

Route::view('/termos/termo-adesao', 'legal.termo-adesao-v2')->name('termos.termo-adesao');
Route::view('/termos/politica-privacidade', 'legal.politica-privacidade-v2')->name('termos.politica-privacidade');

// STORY-013 — confirmação de email + reenvio.
Route::get('/email/confirmar/{usuario}', [EmailConfirmacaoController::class, 'confirmar'])
    ->middleware('signed')
    ->name('email.confirmar');
Route::view('/email/confirmado', 'email.confirmado')->name('email.confirmado');
Route::view('/email/confirmar-erro', 'email.confirmar-erro')->name('email.confirmar-erro');
Route::post('/email/reenviar-confirmacao', [EmailConfirmacaoController::class, 'reenviar'])
    ->name('email.reenviar');

Route::middleware('auth')->group(function () {
    // STORY-016 — "Minhas Empresas" substitui a /home mínima da STORY-011.
    Route::get('/home', MinhasEmpresas::class)->name('home');
    Route::post('/logout', [HomeController::class, 'logout'])->name('logout');
    // STORY-066 — fora do grupo do backoffice: quem chama está logado como o usuário assumido.
    Route::post('/personificacao/encerrar', [PersonificacaoController::class, 'encerrar'])->name('personificacao.encerrar');

    // STORY-014 — cadastro manual da primeira Empresa Analisada + visualização.
    Route::get('/empresas/nova', CadastrarEmpresa::class)->name('empresas.nova');
    // Lista CNAE para o widget de busca (carregada sob demanda).
    Route::get('/cnaes', [CnaeController::class, 'index'])->name('cnaes.index');
    Route::get('/empresas/{empresa}', [EmpresaController::class, 'show'])
        ->whereUuid('empresa')
        ->name('empresas.show');

    // STORY-027 — quiz de diagnóstico (Indústria) + seletor de empresa quando
    // Roberto entra pelo menu sem ter clicado em um card de empresa.
    Route::get('/diagnosticos/novo', SelecionarEmpresaDiagnostico::class)->name('diagnosticos.selecionar');
    Route::get('/empresas/{empresa}/diagnosticos/novo', QuizDiagnostico::class)
        ->whereUuid('empresa')
        ->name('diagnosticos.novo');

    // STORY-029 — relatório minimalista. Global Scope do model garante 404
    // cross-tenant (IDR-009).
    Route::get('/diagnosticos/{diagnostico}', [DiagnosticoController::class, 'show'])
        ->whereUuid('diagnostico')
        ->name('diagnosticos.show');
    Route::get('/diagnosticos/{diagnostico}/pdf', [DiagnosticoController::class, 'pdf'])
        ->whereUuid('diagnostico')
        ->name('diagnosticos.pdf');

    // STORY-047 — Raio X do Negócio (DRM): lançamento mensal. Empresa de outro usuário → 404 (IDR-009).
    Route::get('/empresas/{empresa}/drm/lancar', LancamentoDrm::class)
        ->whereUuid('empresa')
        ->name('drm.lancar');
    Route::get('/empresas/{empresa}/drm/{competencia}', [DrmMesController::class, 'show'])
        ->whereUuid('empresa')
        ->where('competencia', '\d{4}-(0[1-9]|1[0-2])')
        ->name('drm.mes');
});

// STORY-065, 066 e 067 — backoffice: cada rota exige uma permissão de admin (PDR-013).
// Sem ela: 404 (IDR-009).
Route::middleware('auth')->prefix('backoffice')->name('backoffice.')->group(function () {
    Route::get('/usuarios', BackofficeUsuarios::class)->middleware('backoffice:usuarios.ver')->name('usuarios');
    Route::post('/usuarios/{usuario}/logar-como', [PersonificacaoController::class, 'iniciar'])
        ->middleware('backoffice:usuarios.logar_como')
        ->whereUuid('usuario')
        ->name('usuarios.logar-como');
    Route::get('/administradores', BackofficeAdministradores::class)->middleware('backoffice:admins.gerenciar')->name('administradores');
    Route::get('/glossario', BackofficeGlossario::class)->middleware('backoffice:glossario.editar')->name('glossario');
    Route::get('/recomendacoes', BackofficeRecomendacoes::class)->middleware('backoffice:recomendacoes.editar')->name('recomendacoes');
    Route::get('/planos', BackofficePlanos::class)->middleware('backoffice:planos.gerenciar')->name('planos');
    Route::get('/cupons', BackofficeCupons::class)->middleware('backoffice:cupons.gerenciar')->name('cupons');
});
