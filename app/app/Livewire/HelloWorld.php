<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Jobs\HelloWorldEmail;
use App\Observabilidade\EventLogger;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Throwable;

/**
 * Página viva do hello world (STORY-007 CA-1).
 *
 * Exibe nome do produto, versão deployada e indicador `OK` do healthcheck.
 * Botão dispara `HelloWorldEmail` no worker — exercita os 3 processos da ADR-002 +
 * Mailpit + propagação de `request_id`.
 *
 * Emite evento de produto `hello_world_visualizado` (proxy do `usuario_cadastrado` da
 * ADR-004, idempotente por sessão) — valida ADR-004 §verificação da STORY-007.
 */
final class HelloWorld extends Component
{
    public string $status = '...';

    public string $mensagemEnvio = '';

    public function mount(): void
    {
        $this->status = $this->verificarHealth();

        if (! session()->has('hello_emitido')) {
            EventLogger::emit('hello_world_visualizado', [
                'origem' => 'phase_1_local',
            ]);
            session()->put('hello_emitido', true);
        }
    }

    public function dispararEmail(): void
    {
        HelloWorldEmail::dispatch('hello@defonline.local');
        $this->mensagemEnvio = 'Job enfileirado — veja Mailpit em http://localhost:8025.';
    }

    public function render(): View
    {
        return view('livewire.hello-world', [
            'appName' => Config::get('app.name', 'DEFOnline'),
            'version' => Config::get('app.version', 'dev'),
            'env' => Config::get('app.env'),
            'requestId' => request_id(),
        ]);
    }

    private function verificarHealth(): string
    {
        try {
            DB::select('select 1');

            return 'OK';
        } catch (Throwable) {
            return 'FALHA';
        }
    }
}
