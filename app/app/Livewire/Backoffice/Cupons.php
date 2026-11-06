<?php

declare(strict_types=1);

namespace App\Livewire\Backoffice;

use App\Domain\Cobranca\CupomRegras;
use App\Domain\Cobranca\TipoDesconto;
use App\Livewire\Backoffice\Concerns\ExigePermissao;
use App\Models\Cupom;
use App\Models\CupomItem;
use App\Models\PacoteCredito;
use App\Models\Plano;
use App\Models\Usuario;
use App\Observabilidade\AuditLogger;
use App\Support\DataExibicao;
use App\Support\PermissaoAdmin;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * STORY-072 / PDR-020 / spec §2.5 — cupons de desconto no backoffice: criar, editar, desativar,
 * conceder a contas e acompanhar concessões e usos. A aplicação no checkout é da STORY-083,
 * que reutiliza as regras de `App\Domain\Cobranca`.
 *
 * Os itens cobertos são referências a Plano (com o ciclo) e a PacoteCredito pelo id, nunca por código.
 */
#[Layout('components.layouts.app')]
final class Cupons extends Component
{
    use ExigePermissao;

    public const INTEIRO_MAX = 100000;

    public const DETALHE_LIMITE = 200;

    /** @var array{id: ?string, codigo: string, tipo: string, valor: string, vigencia_inicio: string, vigencia_fim: string, limite_usos: string, duracao: string, ciclos: string, itens: list<string>, ativo: bool} */
    public array $cupom = [
        'id' => null, 'codigo' => '', 'tipo' => 'percentual', 'valor' => '', 'vigencia_inicio' => '', 'vigencia_fim' => '',
        'limite_usos' => '', 'duracao' => 'assinatura', 'ciclos' => '', 'itens' => [], 'ativo' => true,
    ];

    /** @var array{cupom_id: ?string, modo: string, emails: string, ate: string} */
    public array $concessao = ['cupom_id' => null, 'modo' => 'contas', 'emails' => '', 'ate' => ''];

    /**
     * Resultado do "calcular": o que a concessão fará, antes de confirmar.
     *
     * @var array{total: int, novas: int, ja_tinham: int}|null
     */
    public ?array $previa = null;

    public ?string $mensagem = null;

    public ?string $detalhe = null;

    public function mount(): void
    {
        $this->auditar('backoffice.cupons_listados', 'Cupom');
        $this->limparCupom();
    }

    public function salvarCupom(): void
    {
        $this->cupom['codigo'] = CupomRegras::normalizarCodigo($this->cupom['codigo']);

        $this->validate([
            'cupom.codigo' => ['required', 'regex:/^[A-Z0-9_-]{3,40}$/', $this->regraCodigoUnico()],
            'cupom.tipo' => ['required', 'in:percentual,valor_fixo'],
            'cupom.valor' => ['required', $this->regraValor()],
            'cupom.vigencia_inicio' => ['required', 'date_format:Y-m-d'],
            'cupom.vigencia_fim' => ['required', 'date_format:Y-m-d', $this->regraVigencia()],
            'cupom.limite_usos' => ['nullable', 'integer', 'min:1', 'max:'.self::INTEIRO_MAX, $this->regraLimite()],
            'cupom.duracao' => ['required', 'in:assinatura,ciclos'],
            'cupom.ciclos' => ['exclude_unless:cupom.duracao,ciclos', 'required', 'integer', 'min:1', 'max:'.self::INTEIRO_MAX],
            'cupom.itens' => ['required', 'array', 'min:1'],
            'cupom.itens.*' => ['string', $this->regraItem()],
        ], [
            'cupom.codigo.required' => 'Informe o código do cupom.',
            'cupom.codigo.regex' => 'O código deve ter de 3 a 40 caracteres: letras, números, hífen ou sublinhado, sem espaços.',
            'cupom.tipo.required' => 'Escolha o tipo de desconto.',
            'cupom.tipo.in' => 'Escolha percentual ou valor fixo.',
            'cupom.valor.required' => 'Informe o valor do desconto.',
            'cupom.vigencia_inicio.required' => 'Informe o início da vigência.',
            'cupom.vigencia_inicio.date_format' => 'Informe uma data válida.',
            'cupom.vigencia_fim.required' => 'Informe o fim da vigência.',
            'cupom.vigencia_fim.date_format' => 'Informe uma data válida.',
            'cupom.limite_usos.integer' => 'O limite deve ser um número inteiro.',
            'cupom.limite_usos.min' => 'O limite deve ser de pelo menos 1 uso. Deixe em branco para não limitar.',
            'cupom.limite_usos.max' => 'O limite é grande demais.',
            'cupom.duracao.required' => 'Escolha a duração do desconto.',
            'cupom.duracao.in' => 'Escolha ciclos ou toda a assinatura.',
            'cupom.ciclos.required' => 'Informe quantos ciclos o desconto dura.',
            'cupom.ciclos.integer' => 'Os ciclos devem ser um número inteiro.',
            'cupom.ciclos.min' => 'O desconto deve durar pelo menos 1 ciclo.',
            'cupom.ciclos.max' => 'Os ciclos são grandes demais.',
            'cupom.itens.required' => 'Escolha ao menos um item coberto.',
            'cupom.itens.min' => 'Escolha ao menos um item coberto.',
        ]);

        $dados = [
            'codigo' => $this->cupom['codigo'],
            'tipo' => $this->cupom['tipo'],
            'valor' => $this->valorEmInteiro(),
            'vigencia_inicio' => $this->cupom['vigencia_inicio'],
            'vigencia_fim' => $this->cupom['vigencia_fim'],
            'limite_usos' => $this->cupom['limite_usos'] === '' ? null : (int) $this->cupom['limite_usos'],
            'ciclos_duracao' => $this->cupom['duracao'] === 'ciclos' ? (int) $this->cupom['ciclos'] : null,
        ];
        $itens = array_values(array_unique($this->cupom['itens']));
        sort($itens);

        try {
            DB::transaction(fn () => $this->gravar($this->cupom['id'], $dados, $itens));
        } catch (UniqueConstraintViolationException) {
            // Corrida entre dois admins com o mesmo código: o índice único decide.
            $this->addError('cupom.codigo', 'Já existe um cupom com este código.');

            return;
        }

        $this->limparCupom();
    }

    /** 25 como percentual não é R$ 25: trocar o tipo limpa o valor, e a máscara do novo tipo recomeça. */
    public function updatedCupom(mixed $valor, string $campo): void
    {
        if ($campo === 'tipo') {
            $this->cupom['valor'] = '';
            $this->resetValidation('cupom.valor');
        }
    }

    public function gerarCodigo(): void
    {
        do {
            $codigo = CupomRegras::gerarCodigo();
        } while (Cupom::query()->where('codigo', $codigo)->exists());

        $this->cupom['codigo'] = $codigo;
        $this->resetValidation('cupom.codigo');
    }

    public function editarCupom(string $id): void
    {
        $cupom = Cupom::query()->with('itens')->findOrFail($id);

        $this->resetValidation();
        $this->cupom = [
            'id' => $cupom->id,
            'codigo' => $cupom->codigo,
            'tipo' => $cupom->tipo->value,
            'valor' => $cupom->tipo === TipoDesconto::Percentual ? TipoDesconto::percentualEmTexto($cupom->valor) : Planos::reais($cupom->valor),
            'vigencia_inicio' => $cupom->vigencia_inicio->toDateString(),
            'vigencia_fim' => $cupom->vigencia_fim->toDateString(),
            'limite_usos' => $cupom->limite_usos === null ? '' : (string) $cupom->limite_usos,
            'duracao' => $cupom->ciclos_duracao === null ? 'assinatura' : 'ciclos',
            'ciclos' => $cupom->ciclos_duracao === null ? '' : (string) $cupom->ciclos_duracao,
            'itens' => $cupom->itens->map(fn (CupomItem $i): string => $i->chave())->sort()->values()->all(),
            'ativo' => $cupom->ativo,
        ];
    }

    /** CA-6: desativar impede novos usos e concessões; descontos já aplicados seguem. */
    public function alternarCupom(string $id): void
    {
        $cupom = Cupom::query()->findOrFail($id);
        $ativo = ! $cupom->ativo;

        $cupom->forceFill(['ativo' => $ativo, 'alterado_por_usuario_id' => Auth::id()])->save();

        $this->auditar($ativo ? 'cupons.cupom_reativado' : 'cupons.cupom_desativado', 'Cupom', $cupom->id, ['ativo' => ! $ativo], ['ativo' => $ativo]);

        if (! $ativo && $this->concessao['cupom_id'] === $id) {
            $this->cancelarConcessao();
        }
    }

    public function limparCupom(): void
    {
        $this->resetValidation();
        $this->cupom = [
            'id' => null, 'codigo' => '', 'tipo' => 'percentual', 'valor' => '', 'vigencia_inicio' => '', 'vigencia_fim' => '',
            'limite_usos' => '', 'duracao' => 'assinatura', 'ciclos' => '', 'itens' => [], 'ativo' => true,
        ];
    }

    public function abrirConcessao(string $id): void
    {
        $cupom = Cupom::query()->findOrFail($id);
        abort_unless($cupom->ativo, 404);

        $this->resetValidation();
        $this->mensagem = null;
        $this->previa = null;
        $this->concessao = ['cupom_id' => $id, 'modo' => 'contas', 'emails' => '', 'ate' => ''];
    }

    public function cancelarConcessao(): void
    {
        $this->resetValidation();
        $this->previa = null;
        $this->concessao = ['cupom_id' => null, 'modo' => 'contas', 'emails' => '', 'ate' => ''];
    }

    /** Qualquer mudança no formulário invalida a prévia: o admin só confirma o que viu. */
    public function updatedConcessao(): void
    {
        $this->previa = null;
        $this->mensagem = null;
    }

    /** CA-4: mostra quantas contas serão afetadas antes de confirmar. */
    public function calcularConcessao(): void
    {
        $this->mensagem = null;
        $this->previa = $this->resumoDaConcessao();
    }

    public function confirmarConcessao(): void
    {
        $resumo = $this->resumoDaConcessao();
        $cupom = Cupom::query()->findOrFail($this->concessao['cupom_id']);
        $agora = now();
        $porContas = $this->concessao['modo'] === 'contas';

        $novas = 0;
        $idsNovos = [];
        $idsExistentes = $porContas ? $this->idsJaConcedidos($cupom->id) : [];

        DB::transaction(function () use ($cupom, $agora, $porContas, $idsExistentes, $resumo, &$novas, &$idsNovos): void {
            $this->contasAlvo()->select('usuarios.id')->orderBy('usuarios.id')->chunkById(500, function ($contas) use ($cupom, $agora, $porContas, $idsExistentes, &$novas, &$idsNovos): void {
                $linhas = $contas->map(fn (Usuario $c): array => [
                    'id' => (string) Str::uuid7(),
                    'cupom_id' => $cupom->id,
                    'usuario_id' => $c->id,
                    'ciclos_restantes' => $cupom->ciclos_duracao,
                    'concedida_por_usuario_id' => Auth::id(),
                    'concedida_em' => $agora,
                ])->all();

                // CA-5: o índice único (cupom, usuário) descarta o que já existe, sem duplicar.
                $novas += DB::table('cupom_concessoes')->insertOrIgnore($linhas);

                if ($porContas) {
                    $idsNovos = [...$idsNovos, ...array_values(array_diff($contas->pluck('id')->all(), $idsExistentes))];
                }
            }, 'usuarios.id', 'id');

            $this->auditar('cupons.cupom_concedido', 'Cupom', $cupom->id, null, [
                'codigo' => $cupom->codigo,
                'modo' => $this->concessao['modo'],
                'cadastradas_ate' => $porContas ? null : $this->concessao['ate'],
                'concedidas' => $novas,
                'ja_tinham' => $resumo['total'] - $novas,
                'usuarios' => $porContas ? $idsNovos : null,
            ]);
        });

        $this->mensagem = $novas.($novas === 1 ? ' conta recebeu' : ' contas receberam').' o cupom '.$cupom->codigo
            .($resumo['total'] - $novas > 0 ? ' ('.($resumo['total'] - $novas).' já tinham).' : '.');
        $this->cancelarConcessao();
    }

    public function detalhar(string $id): void
    {
        Cupom::query()->findOrFail($id);
        $this->detalhe = $this->detalhe === $id ? null : $id;
    }

    public function render(): View
    {
        $cupons = Cupom::query()
            ->with(['itens.plano', 'itens.pacote'])
            ->withCount(['concessoes', 'usos'])
            ->orderByDesc('created_at')
            ->get();

        $opcoes = [];
        foreach (Plano::query()->orderBy('ordem')->orderBy('nome')->get() as $plano) {
            foreach (CupomItem::CICLOS as $ciclo) {
                $opcoes["plano:{$plano->id}:{$ciclo}"] = "{$plano->nome} ({$ciclo})".($plano->ativo ? '' : ' - inativo');
            }
        }
        foreach (PacoteCredito::query()->orderBy('ordem')->orderBy('nome')->get() as $pacote) {
            $opcoes["pacote:{$pacote->id}"] = "Pacote {$pacote->nome}".($pacote->ativo ? '' : ' - inativo');
        }

        $detalhe = $this->detalhe === null ? null : Cupom::query()->find($this->detalhe);

        return view('livewire.backoffice.cupons', [
            'cupons' => $cupons,
            'opcoes' => $opcoes,
            'cupomConcessao' => $this->concessao['cupom_id'] === null ? null : $cupons->firstWhere('id', $this->concessao['cupom_id']),
            'detalheCupom' => $detalhe,
            'concessoesDetalhe' => $detalhe?->concessoes()->with('usuario')->orderByDesc('concedida_em')->limit(self::DETALHE_LIMITE)->get(),
            'usosDetalhe' => $detalhe?->usos()->with('usuario')->orderByDesc('usado_em')->limit(self::DETALHE_LIMITE)->get(),
        ])->layoutData(['title' => 'Cupons']);
    }

    protected function permissaoExigida(): PermissaoAdmin
    {
        return PermissaoAdmin::GerenciarCupons;
    }

    /**
     * Valida o formulário de concessão e conta o efeito, sem gravar.
     *
     * @return array{total: int, novas: int, ja_tinham: int}
     */
    private function resumoDaConcessao(): array
    {
        $this->validate([
            'concessao.cupom_id' => ['required', $this->regraCupomAtivo()],
            'concessao.modo' => ['required', 'in:contas,todas'],
            'concessao.emails' => ['exclude_unless:concessao.modo,contas', 'required', $this->regraEmails()],
            'concessao.ate' => ['exclude_unless:concessao.modo,todas', 'required', 'date_format:Y-m-d'],
        ], [
            'concessao.cupom_id.required' => 'Escolha o cupom.',
            'concessao.modo.in' => 'Escolha contas específicas ou todas as contas.',
            'concessao.emails.required' => 'Informe ao menos um e-mail.',
            'concessao.ate.required' => 'Informe a data limite de cadastro.',
            'concessao.ate.date_format' => 'Informe uma data válida.',
        ]);

        $total = (clone $this->contasAlvo())->count();
        $jaTinham = (clone $this->contasAlvo())
            ->whereIn('usuarios.id', DB::table('cupom_concessoes')->select('usuario_id')->where('cupom_id', $this->concessao['cupom_id']))
            ->count();

        return ['total' => $total, 'novas' => $total - $jaTinham, 'ja_tinham' => $jaTinham];
    }

    /**
     * Contas atingidas: as dos e-mails informados, ou todas as cadastradas até o fim do dia
     * da data. Fora ficam contas excluídas e anonimizadas.
     *
     * @return Builder<Usuario>
     */
    private function contasAlvo(): Builder
    {
        $contas = Usuario::query()->whereNull('usuarios.anonimizado_at');

        if ($this->concessao['modo'] === 'contas') {
            return $contas->whereIn(DB::raw('lower(usuarios.email)'), self::emailsInformados($this->concessao['emails']));
        }

        // A data é um dia do calendário de Brasília (o admin escolhe uma data local): "até o fim do dia" é até
        // 23:59:59 de Brasília, que em UTC é 02:59:59 do dia seguinte (IDR-025).
        return $contas->where('usuarios.created_at', '<', DataExibicao::inicioDoDiaSeguinte($this->concessao['ate'])->format('Y-m-d H:i:sP'));
    }

    /** @return list<string> */
    private function idsJaConcedidos(string $cupomId): array
    {
        return DB::table('cupom_concessoes')->where('cupom_id', $cupomId)->pluck('usuario_id')->all();
    }

    /** @return list<string> e-mails em minúsculas, sem repetição */
    private static function emailsInformados(string $texto): array
    {
        $partes = preg_split('/[\s,;]+/', mb_strtolower($texto), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return array_values(array_unique($partes));
    }

    private function regraEmails(): Closure
    {
        return function (string $atributo, mixed $valor, Closure $falhar): void {
            $informados = self::emailsInformados((string) $valor);

            if ($informados === []) {
                $falhar('Informe ao menos um e-mail.');

                return;
            }

            $achados = Usuario::query()->whereNull('anonimizado_at')
                ->whereIn(DB::raw('lower(email)'), $informados)
                ->pluck('email')->map(fn (string $e): string => mb_strtolower($e))->all();
            $faltam = array_values(array_diff($informados, $achados));

            if ($faltam !== []) {
                $falhar('Nenhuma conta com: '.implode(', ', $faltam).'. Corrija ou remova para continuar.');
            }
        };
    }

    private function regraCupomAtivo(): Closure
    {
        return function (string $atributo, mixed $valor, Closure $falhar): void {
            $cupom = Cupom::query()->find((string) $valor);

            if ($cupom === null) {
                $falhar('Cupom não encontrado.');
            } elseif (! $cupom->ativo) {
                $falhar('Cupom desativado não pode ser concedido.');
            }
        };
    }

    private function regraCodigoUnico(): Closure
    {
        return function (string $atributo, mixed $valor, Closure $falhar): void {
            $existe = Cupom::query()
                ->where('codigo', CupomRegras::normalizarCodigo((string) $valor))
                ->when($this->cupom['id'], fn ($q, $id) => $q->where('id', '!=', $id))
                ->exists();

            if ($existe) {
                $falhar('Já existe um cupom com este código.');
            }
        };
    }

    /** Formato do valor e faixa: a regra de faixa é do domínio. */
    private function regraValor(): Closure
    {
        return function (string $atributo, mixed $valor, Closure $falhar): void {
            $inteiro = $this->valorEmInteiro();

            if ($inteiro === null) {
                $falhar($this->cupom['tipo'] === 'percentual' ? 'Informe o percentual com até uma casa decimal, de 0,1 a 100.' : 'Informe o valor em reais, por exemplo 50,00.');

                return;
            }

            $erros = $this->errosDaDefinicao($inteiro);
            if (isset($erros['valor'])) {
                $falhar($erros['valor']);
            }
        };
    }

    private function regraVigencia(): Closure
    {
        return function (string $atributo, mixed $valor, Closure $falhar): void {
            $erros = $this->errosDaDefinicao($this->valorEmInteiro() ?? 1);

            if (isset($erros['vigencia_fim'])) {
                $falhar($erros['vigencia_fim']);
            }
        };
    }

    /** Não deixa o limite ficar abaixo dos usos já feitos. */
    private function regraLimite(): Closure
    {
        return function (string $atributo, mixed $valor, Closure $falhar): void {
            $id = $this->cupom['id'];
            $usos = $id === null ? 0 : DB::table('cupom_usos')->where('cupom_id', $id)->count();

            if ($usos > (int) $valor) {
                $falhar("O limite não pode ser menor que os {$usos} usos já feitos.");
            }
        };
    }

    private function regraItem(): Closure
    {
        return function (string $atributo, mixed $valor, Closure $falhar): void {
            if (self::resolverItem((string) $valor) === null) {
                $falhar('Item inválido.');
            }
        };
    }

    /**
     * "plano:{id}:{ciclo}" ou "pacote:{id}" para as colunas de CupomItem, se o plano ou pacote existir.
     *
     * @return array{plano_id: ?string, ciclo: ?string, pacote_credito_id: ?string}|null
     */
    private static function resolverItem(string $chave): ?array
    {
        $partes = explode(':', $chave);

        if (count($partes) === 3 && $partes[0] === 'plano' && in_array($partes[2], CupomItem::CICLOS, true) && Str::isUuid($partes[1]) && Plano::query()->whereKey($partes[1])->exists()) {
            return ['plano_id' => $partes[1], 'ciclo' => $partes[2], 'pacote_credito_id' => null];
        }

        if (count($partes) === 2 && $partes[0] === 'pacote' && Str::isUuid($partes[1]) && PacoteCredito::query()->whereKey($partes[1])->exists()) {
            return ['plano_id' => null, 'ciclo' => null, 'pacote_credito_id' => $partes[1]];
        }

        return null;
    }

    /** Percentual em décimos, ou centavos do valor fixo. Texto com "-" vira negativo para o domínio recusar. */
    private function valorEmInteiro(): ?int
    {
        $texto = trim($this->cupom['valor']);

        if ($this->cupom['tipo'] === 'percentual') {
            if (preg_match('/^(-?)(\d{1,6})(?:,(\d))?$/', $texto, $partes) !== 1) {
                return null;
            }

            $decimos = (int) $partes[2] * 10 + (int) ($partes[3] ?? 0);

            return $partes[1] === '-' ? -$decimos : $decimos;
        }

        $negativo = str_starts_with($texto, '-');
        $centavos = Planos::centavos(ltrim($texto, '- '));

        if ($centavos === null) {
            return null;
        }

        return $negativo ? -$centavos : $centavos;
    }

    /** @return array<string, string> */
    private function errosDaDefinicao(int $valor): array
    {
        $tipo = TipoDesconto::tryFrom($this->cupom['tipo']);
        $inicio = $this->data($this->cupom['vigencia_inicio']);
        $fim = $this->data($this->cupom['vigencia_fim']);

        if ($tipo === null || $inicio === null || $fim === null) {
            return [];
        }

        return CupomRegras::errosDaDefinicao($tipo, $valor, $inicio, $fim);
    }

    private function data(string $texto): ?CarbonImmutable
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $texto) !== 1) {
            return null;
        }

        $data = CarbonImmutable::createFromFormat('!Y-m-d', $texto);

        // 2026-02-30 "transborda" para março: só vale a data que volta igual.
        return $data->format('Y-m-d') === $texto ? $data : null;
    }

    /**
     * Grava só se mudou algo e audita antes, depois e autor (CA-8).
     *
     * @param  array<string, mixed>  $dados
     * @param  list<string>  $itens  chaves ordenadas
     */
    private function gravar(?string $id, array $dados, array $itens): void
    {
        $cupom = $id === null ? new Cupom : Cupom::query()->with('itens')->findOrFail($id);
        $existia = $cupom->exists;
        $campos = array_keys($dados);

        $antes = $existia ? [...$this->comoTexto($cupom->only($campos)), 'itens' => $cupom->itens->map(fn (CupomItem $i): string => $i->chave())->sort()->values()->all()] : null;

        $cupom->fill([...$dados, 'ativo' => $existia ? $cupom->ativo : true, 'alterado_por_usuario_id' => Auth::id()]);

        $depois = [...$this->comoTexto($dados), 'itens' => $itens];

        if ($existia && $antes === $depois) {
            return;
        }

        $cupom->save();

        if (! $existia || $antes['itens'] !== $itens) {
            $cupom->itens()->delete();
            foreach ($itens as $chave) {
                /** @var array<string, string|null> $colunas */
                $colunas = self::resolverItem($chave);
                $cupom->itens()->create($colunas);
            }
        }

        $this->auditar($existia ? 'cupons.cupom_alterado' : 'cupons.cupom_criado', 'Cupom', $cupom->id, $antes, $depois);
    }

    /**
     * Normaliza para comparar antes e depois: o enum, as datas e os números viram escalares.
     *
     * @param  array<string, mixed>  $dados
     * @return array<string, mixed>
     */
    private function comoTexto(array $dados): array
    {
        return array_map(function (mixed $v): mixed {
            return match (true) {
                $v instanceof TipoDesconto => $v->value,
                $v instanceof \DateTimeInterface => $v->format('Y-m-d'),
                default => $v,
            };
        }, $dados);
    }

    /**
     * @param  array<string, mixed>|null  $antes
     * @param  array<string, mixed>|null  $depois
     */
    private function auditar(string $action, string $tipo, ?string $subjectId = null, ?array $antes = null, ?array $depois = null): void
    {
        $atorId = (string) Auth::id();

        AuditLogger::log(
            action: $action,
            subjectType: $tipo,
            subjectId: $subjectId,
            actorType: 'user',
            actorId: $atorId,
            usuarioId: $atorId,
            before: $antes,
            after: $depois,
        );
    }
}
