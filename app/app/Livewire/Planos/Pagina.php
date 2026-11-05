<?php

declare(strict_types=1);

namespace App\Livewire\Planos;

use App\Domain\Cobranca\Carteira;
use App\Domain\Cobranca\StatusAssinatura;
use App\Domain\Cobranca\VitrinePrecos;
use App\Models\Assinatura;
use App\Models\CobrancaConfig;
use App\Models\PacoteCredito;
use App\Models\Plano;
use App\Models\TrialConfig;
use App\Support\Cobranca\RotasCobranca;
use App\Support\Personificacao;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Throwable;

/**
 * STORY-075 / PDR-020 — planos, créditos avulsos e teste, lidos do catálogo a cada render
 * (alterou no backoffice, aparece aqui sem deploy). Nada de preço, nome ou cota em código.
 *
 * Pública: o visitante vê o catálogo e é levado ao cadastro; o logado segue para o checkout do
 * item (rotas das STORY-074/076/077/082, via {@see RotasCobranca}). Com a cobrança desligada
 * (STORY-073 CA-1) a rota não existe para ninguém: 404.
 */
final class Pagina extends Component
{
    public string $ciclo = 'mensal';

    public function mount(): void
    {
        abort_unless(CobrancaConfig::ligada(), 404);
    }

    public function updatedCiclo(): void
    {
        if (! in_array($this->ciclo, ['mensal', 'anual'], true)) {
            $this->ciclo = 'mensal';
        }
    }

    public function render(): View
    {
        $logado = auth()->check();

        $dados = $this->catalogo();

        $view = view('livewire.planos.pagina', [
            ...$dados,
            'logado' => $logado,
            'personificando' => Personificacao::ativa() !== null,
            'anual' => $this->ciclo === 'anual',
            'urlCadastro' => route('cadastro'),
            'urlTeste' => RotasCobranca::url(RotasCobranca::TESTE),
            'pacoteUrl' => fn (PacoteCredito $p): ?string => RotasCobranca::url(RotasCobranca::COMPRAR, ['pacote' => $p->id]),
            'planoUrl' => fn (Plano $p, bool $troca): ?string => RotasCobranca::url(
                $troca ? RotasCobranca::TROCAR : RotasCobranca::ASSINAR,
                ['plano' => $p->id, 'ciclo' => $this->ciclo],
            ),
        ]);

        return $view->layout($logado ? 'components.layouts.app' : 'components.layouts.auth', [
            'title' => 'Planos e preços',
            'largo' => true,
        ]);
    }

    /**
     * @return array{erro: bool, planos: iterable<int, Plano>, pacotes: iterable<int, PacoteCredito>, trial: ?TrialConfig, planoAtualId: ?string, economiaMax: int}
     */
    private function catalogo(): array
    {
        try {
            // Transação para a falha de uma consulta não derrubar a transação que o chamador já tenha aberto.
            [$planos, $pacotes, $trial, $planoAtualId] = DB::transaction(fn (): array => [
                Plano::query()->where('ativo', true)->orderBy('ordem')->orderBy('nome')->get(),
                PacoteCredito::query()->where('ativo', true)->orderBy('ordem')->orderBy('nome')->get(),
                $this->trialOferecido(),
                $this->planoAtualId(),
            ]);
        } catch (Throwable) {
            return ['erro' => true, 'planos' => [], 'pacotes' => [], 'trial' => null, 'planoAtualId' => null, 'economiaMax' => 0];
        }

        $economiaMax = $planos->map(fn (Plano $p): int => VitrinePrecos::economiaPercentual($p->preco_mensal_centavos, $p->preco_anual_centavos))->max() ?? 0;

        return ['erro' => false, 'planos' => $planos, 'pacotes' => $pacotes, 'trial' => $trial, 'planoAtualId' => $planoAtualId, 'economiaMax' => $economiaMax];
    }

    /** A configuração do teste, só se estiver ativa e (para quem está logado) o Usuário puder usá-lo. */
    private function trialOferecido(): ?TrialConfig
    {
        $config = TrialConfig::query()->first();

        if ($config === null || ! $config->ativo) {
            return null;
        }

        $id = auth()->id();

        return $id === null || app(Carteira::class)->trialElegivel((string) $id) ? $config : null;
    }

    private function planoAtualId(): ?string
    {
        $id = auth()->id();

        if ($id === null) {
            return null;
        }

        return Assinatura::query()
            ->where('usuario_id', $id)
            ->where('trial', false)
            ->whereIn('status', [StatusAssinatura::Ativa->value, StatusAssinatura::EmAtraso->value])
            ->value('plano_id');
    }
}
