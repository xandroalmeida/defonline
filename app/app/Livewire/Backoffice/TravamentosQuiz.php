<?php

declare(strict_types=1);

namespace App\Livewire\Backoffice;

use App\Domain\Quiz\CatalogoPerguntas;
use App\Livewire\Backoffice\Concerns\ExigePermissao;
use App\Observabilidade\EventosQuiz;
use App\Support\PermissaoAdmin;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * STORY-105 — onde os clientes travam no quiz, por pergunta e por regra de consistência, num
 * período. Fontes: os eventos `quiz_travamento` (registrados desde esta estória) e os avisos aceitos
 * que já ficavam gravados em cada diagnóstico. O que a equipe fez pelo "Logar como" fica de fora.
 * Só contagens: nenhum valor nem dado pessoal.
 */
#[Layout('components.layouts.app')]
final class TravamentosQuiz extends Component
{
    use ExigePermissao;

    public const PERIODOS = [30 => 'Últimos 30 dias', 90 => 'Últimos 90 dias', 365 => 'Último ano'];

    #[Url(as: 'dias', except: 90)]
    public int $dias = 90;

    public function render(): View
    {
        $dias = array_key_exists($this->dias, self::PERIODOS) ? $this->dias : 90;
        $desde = now()->subDays($dias);

        $eventos = DB::table('evento_produto')
            ->where('nome_evento', EventosQuiz::TRAVAMENTO)
            ->where('ocorrido_em', '>=', $desde)
            ->whereRaw("propriedades->'personificacao' IS NULL")
            ->selectRaw("propriedades->>'tipo' as tipo, propriedades->>'chave' as chave, count(*) as vezes, count(distinct propriedades->>'quiz_id') as quizzes")
            ->groupByRaw("propriedades->>'tipo', propriedades->>'chave'")
            ->get();

        $porCampo = $eventos->where('tipo', EventosQuiz::TIPO_CAMPO)->sortByDesc('vezes')->values();

        $aceitos = DB::table('diagnosticos')
            ->crossJoin(DB::raw("jsonb_array_elements(coalesce(diagnosticos.quiz_payload->'alertas_aceitos', '[]'::jsonb)) as aceito"))
            ->whereNull('diagnosticos.deleted_at')
            ->whereNull('diagnosticos.personificado_por_id')
            ->where('diagnosticos.gerado_em', '>=', $desde)
            ->selectRaw("aceito->>'regra' as regra, count(*) as vezes")
            ->groupByRaw("aceito->>'regra'")
            ->pluck('vezes', 'regra');

        $regras = [];
        foreach (array_keys(CatalogoPerguntas::REGRAS) as $regra) {
            $mostrado = $eventos->first(fn ($e) => $e->chave === $regra && in_array($e->tipo, [EventosQuiz::TIPO_AVISO, EventosQuiz::TIPO_BLOQUEIO], true));
            $regras[$regra] = [
                'titulo' => CatalogoPerguntas::regra($regra),
                'mostrado' => (int) ($mostrado->vezes ?? 0),
                'aceito' => (int) ($aceitos[$regra] ?? 0),
                'bloqueio' => ($mostrado->tipo ?? null) === EventosQuiz::TIPO_BLOQUEIO || $regra === 'R4',
            ];
        }

        $funil = DB::table('evento_produto')
            ->whereIn('nome_evento', ['quiz_iniciado', 'diagnostico_concluido'])
            ->where('ocorrido_em', '>=', $desde)
            ->whereRaw("propriedades->'personificacao' IS NULL")
            ->selectRaw('nome_evento, count(*) as total')
            ->groupBy('nome_evento')
            ->pluck('total', 'nome_evento');

        return view('livewire.backoffice.travamentos-quiz', [
            'porCampo' => $porCampo,
            'regras' => $regras,
            'iniciados' => (int) ($funil['quiz_iniciado'] ?? 0),
            'concluidos' => (int) ($funil['diagnostico_concluido'] ?? 0),
        ])->layoutData(['title' => 'Onde os clientes travam']);
    }

    protected function permissaoExigida(): PermissaoAdmin
    {
        return PermissaoAdmin::VerRespostasQuiz;
    }
}
