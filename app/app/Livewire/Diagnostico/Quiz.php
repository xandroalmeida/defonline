<?php

declare(strict_types=1);

namespace App\Livewire\Diagnostico;

use App\Actions\CalcularDiagnostico;
use App\Domain\Cpf;
use App\Domain\Quiz\Alerta;
use App\Domain\Quiz\ValidacoesCruzadas;
use App\Models\EmpresaAnalisada;
use App\Models\QuizRascunho;
use App\Observabilidade\EventLogger;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Throwable;

/**
 * Quiz de Indústria — 23 campos do Anexo A em 4 blocos (STORY-027).
 *
 * - **Bloco 1 (Identificação):** Q01 (setor — readonly = "industria" no EPIC-002).
 * - **Bloco 2 (DRE/Operação):** Q08, Q09, Q14, Q15, Q16, Q10, Q11, Q12, Q13.
 * - **Bloco 3 (Balanço):** Q02, Q03, Q04, Q05, Q06, Q07.
 * - **Bloco 4 (Contexto/Captação):** Q17 (sim/não) → Q18, Q19, Q20 (R$), Q21, Q22, Q23 (CPF).
 *
 * **Rascunho persistido (IDR-010 + STORY-027 CA-6).** A cada `Próximo` o payload
 * parcial é gravado em `quiz_rascunhos` (NÃO em `diagnosticos`). UNIQUE parcial
 * `(usuario_id, empresa_analisada_id)` garante 1 ativo por par. Expira em 90 dias.
 *
 * **Anti-duplo-submit (CA-9, IDR-010).** Sem dedup em banco — `wire:loading.attr="disabled"`
 * no botão `Calcular diagnóstico` cobre clique duplo/F5 acidental.
 *
 * **Decimais BR → canônico.** Properties guardam string com vírgula
 * ("1.234,56"); a conversão para float canônico ("1234.56") acontece no submit,
 * antes de chamar o {@see CalcularDiagnostico} (o canonicalizer do motor não
 * troca vírgula por ponto — é trabalho do quiz entregar nesse formato).
 *
 * **Multi-tenant.** Empresa resolvida por route binding já passa pelo Global
 * Scope (ADR-003 §Decisão 1) — cross-tenant retorna 404 (IDR-009).
 */
#[Layout('components.layouts.app')]
final class Quiz extends Component
{
    public EmpresaAnalisada $empresa;

    /** Bloco visível no momento (1..4). */
    public int $bloco_atual = 1;

    /** True quando o rascunho já existia ao montar (mostra banner "retomando"). */
    public bool $retomando_rascunho = false;

    /** True quando o rascunho anterior expirou (banner "começamos do início"). */
    public bool $rascunho_expirou = false;

    /**
     * Alertas de validação cruzada pendentes no gate Bloco 3 → 4 (STORY-034).
     * Cada item é o {@see Alerta::toArray()}; vazio = nenhuma inconsistência aberta.
     *
     * @var list<array<string, mixed>>
     */
    public array $alertas_cruzados = [];

    /**
     * Alertas que Roberto optou por ignorar (CA-3). Persistidos em
     * `quiz_payload.alertas_aceitos` no submit — fora do `payload_hash`.
     *
     * @var list<array{regra: string, ocorrido_em: string, valor_envolvido: float|int}>
     */
    public array $alertas_aceitos = [];

    // -------- Bloco 1: Identificação --------
    public string $Q01 = 'industria';   // EPIC-002 só Indústria.

    // -------- Bloco 2: DRE/Operação (9 campos) --------
    public ?string $Q08 = null;  // Compras (R$)

    public ?string $Q09 = null;  // Vendas (R$)

    public ?string $Q14 = null;  // Custos fixos (R$)

    public ?string $Q15 = null;  // Custos variáveis (R$)

    public ?string $Q16 = null;  // Despesas financeiras (R$)

    public ?string $Q10 = null;  // PMC (dias)

    public ?string $Q11 = null;  // PME (dias)

    public ?string $Q12 = null;  // PMR (dias)

    public ?string $Q13 = null;  // Inadimplência (%)

    // -------- Bloco 3: Balanço (6 campos) --------
    public ?string $Q02 = null;  // Caixa/disponibilidades (R$)

    public ?string $Q03 = null;  // Clientes a receber (R$)

    public ?string $Q04 = null;  // Estoque (R$)

    public ?string $Q05 = null;  // Patrimônio imobilizado (R$)

    public ?string $Q06 = null;  // Dívidas financeiras (R$)

    public ?string $Q07 = null;  // Fornecedores a pagar (R$)

    // -------- Bloco 4: Contexto/Captação (7 campos) --------
    public ?string $Q17 = null;  // "sim"/"nao"

    public ?string $Q18 = null;  // Valor a captar (R$)

    public ?string $Q19 = null;  // Endividamento total (R$)

    public ?string $Q20 = null;  // Venda mensal com cartão (R$, deve ser < Q09)

    public ?string $Q21 = null;  // CPF sócio 1

    public ?string $Q22 = null;  // CPF sócio 2

    public ?string $Q23 = null;  // CPF sócio 3

    public function mount(EmpresaAnalisada $empresa): void
    {
        $this->empresa = $empresa;

        // Hidratação: rascunho ativo retoma onde Roberto parou.
        $rascunho = QuizRascunho::paraEmpresa($empresa);

        if ($rascunho !== null) {
            $payload = (array) $rascunho->quiz_payload;
            foreach ($payload as $campo => $valor) {
                if (property_exists($this, $campo)) {
                    $this->{$campo} = $valor;
                }
            }
            $this->bloco_atual = max(1, min(4, $rascunho->ultimo_bloco_preenchido));
            $this->retomando_rascunho = true;

            return;
        }

        // Rascunho expirado ou nunca existiu — verifica se existia um expirado
        // só para mostrar banner amigável.
        $expirado = QuizRascunho::query()
            ->where('empresa_analisada_id', $empresa->id)
            ->where('expires_at', '<=', now())
            ->exists();

        $this->rascunho_expirou = $expirado;
    }

    public function proximoBloco(): void
    {
        $this->validate($this->regrasDoBloco($this->bloco_atual));

        // CA-5: ao sair do Bloco 3 (Balanço), checa inconsistências entre blocos.
        // Se houver alerta ainda não aceito, segura o avanço e mostra o banner inline.
        if ($this->bloco_atual === 3) {
            $alertas = $this->detectarAlertasCruzados();
            if ($alertas !== [] && ! $this->todosAlertasJaAceitos($alertas)) {
                $this->alertas_cruzados = $alertas;

                return;
            }
        }

        $this->avancarBloco();
    }

    public function blocoAnterior(): void
    {
        $this->bloco_atual = max(1, $this->bloco_atual - 1);
        $this->alertas_cruzados = [];
    }

    /**
     * CA-5: Roberto vê os alertas e decide seguir mesmo assim. Registra o "aceito"
     * (CA-3) e avança para o Bloco 4.
     */
    public function continuarComAlertas(): void
    {
        foreach ($this->alertas_cruzados as $alerta) {
            // Dedup por regra — reaceitar a mesma regra (ex.: voltou e avançou de novo) não duplica.
            $this->alertas_aceitos = array_values(array_filter(
                $this->alertas_aceitos,
                fn (array $registro): bool => $registro['regra'] !== $alerta['regra'],
            ));
            $this->alertas_aceitos[] = [
                'regra' => $alerta['regra'],
                'ocorrido_em' => now()->toIso8601String(),
                'valor_envolvido' => $alerta['valor_envolvido'],
            ];
        }

        $this->alertas_cruzados = [];
        $this->avancarBloco();
    }

    /**
     * CA-4/CA-5: leva Roberto direto ao campo suspeito (no bloco correto) e pede
     * foco via evento de browser. Fecha o banner — ele reavalia ao clicar Próximo.
     */
    public function irParaCampo(string $campo): void
    {
        $this->alertas_cruzados = [];
        $this->bloco_atual = $this->blocoDoCampo($campo);
        $this->dispatch('focar-campo', campo: $campo);
    }

    /**
     * Avança um bloco (limitado a 4) e persiste o rascunho. Centraliza o que era
     * o fim do {@see proximoBloco()} para reuso pelo gate de validações cruzadas.
     */
    private function avancarBloco(): void
    {
        $this->bloco_atual = min(4, $this->bloco_atual + 1);
        $this->persistirRascunho();
        $this->retomando_rascunho = false;
        $this->rascunho_expirou = false;
        $this->alertas_cruzados = [];
    }

    /**
     * Roda as validações cruzadas (STORY-034 §6.6) sobre os valores atuais,
     * convertidos para float canônico. Retorna a lista serializável de alertas.
     *
     * @return list<array<string, mixed>>
     */
    private function detectarAlertasCruzados(): array
    {
        $campos = ['Q02', 'Q03', 'Q04', 'Q05', 'Q06', 'Q07', 'Q09', 'Q14', 'Q15', 'Q16'];

        $valores = [];
        foreach ($campos as $campo) {
            $valores[$campo] = self::parseDecimal($this->{$campo});
        }

        return array_map(
            fn (Alerta $alerta): array => $alerta->toArray(),
            app(ValidacoesCruzadas::class)->validar($valores),
        );
    }

    /**
     * True quando toda regra dos `$alertas` já consta em `alertas_aceitos` — evita
     * re-segurar o avanço quando Roberto volta e avança de novo sem mudar os dados.
     *
     * @param  list<array<string, mixed>>  $alertas
     */
    private function todosAlertasJaAceitos(array $alertas): bool
    {
        $aceitos = array_column($this->alertas_aceitos, 'regra');

        foreach ($alertas as $alerta) {
            if (! in_array($alerta['regra'], $aceitos, true)) {
                return false;
            }
        }

        return true;
    }

    /** Bloco do quiz (2 = DRE/Operação, 3 = Balanço) que contém o campo dado. */
    private function blocoDoCampo(string $campo): int
    {
        return in_array($campo, ['Q02', 'Q03', 'Q04', 'Q05', 'Q06', 'Q07'], true) ? 3 : 2;
    }

    /**
     * Nomes humanos por trás dos identificadores Q0X — usados pelas mensagens de
     * validação no lugar do código (CA-3 do Anexo A: copy literal e legível).
     *
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        return [
            'Q01' => 'setor de atividade',
            'Q02' => 'recursos disponíveis',
            'Q03' => 'contas a receber',
            'Q04' => 'estoque',
            'Q05' => 'patrimônio (imobilizado)',
            'Q06' => 'dívidas financeiras',
            'Q07' => 'fornecedores a pagar',
            'Q08' => 'compras (média mensal)',
            'Q09' => 'vendas (média mensal)',
            'Q10' => 'prazo médio de fornecedores (PMC)',
            'Q11' => 'giro de estoque (PME)',
            'Q12' => 'prazo médio a clientes (PMR)',
            'Q13' => 'inadimplência',
            'Q14' => 'custos e despesas fixas',
            'Q15' => 'custos e despesas variáveis',
            'Q16' => 'despesas financeiras',
            'Q17' => 'necessidade de captação',
            'Q18' => 'valor que precisa captar',
            'Q19' => 'endividamento total no mercado',
            'Q20' => 'venda mensal com cartão/duplicatas',
            'Q21' => 'CPF do sócio 1',
            'Q22' => 'CPF do sócio 2',
            'Q23' => 'CPF do sócio 3',
        ];
    }

    /**
     * Mensagens PT-BR para regras nativas usadas no quiz. As Closures custom
     * (regraBrl, regraCpf, etc.) já carregam mensagens próprias em português.
     *
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [
            'required' => 'Informe :attribute.',
            'in' => ':attribute inválido.',
        ];
    }

    public function submeter(CalcularDiagnostico $calcular): mixed
    {
        $this->validate($this->regrasDoBloco(4));

        // Correlação do funil (STORY-035): quiz_id = id do rascunho que originou
        // o diagnóstico; duração = segundos entre o início (created_at do rascunho)
        // e agora. Lido antes do soft-delete do rascunho lá embaixo.
        $rascunho = QuizRascunho::paraEmpresa($this->empresa);
        $quizId = $rascunho?->id;
        $duracaoSeg = $rascunho !== null
            ? (int) $rascunho->created_at->diffInSeconds(now())
            : null;

        try {
            $diagnostico = $calcular->execute(
                $this->empresa,
                $this->payloadCanonico(),
                $this->Q01,
                $this->alertas_aceitos,
                $quizId,
                $duracaoSeg,
            );
        } catch (Throwable $e) {
            // CA-9: exception inesperada não persiste registro parcial (action usa transaction).
            // Loga com request_id (padrão ADR-004) e devolve mensagem genérica.
            $requestId = request()->header('X-Request-Id') ?? (string) Str::uuid();
            Log::error('quiz.diagnostico.falha_inesperada', [
                'request_id' => $requestId,
                'empresa_id' => $this->empresa->id,
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);

            $this->addError('submeter', 'Não foi possível calcular o diagnóstico agora. Tente novamente em alguns instantes (ref: '.substr($requestId, 0, 8).').');

            return null;
        }

        // CA-6: rascunho cumpriu sua função — soft delete preserva trilha de auditoria.
        QuizRascunho::query()
            ->where('empresa_analisada_id', $this->empresa->id)
            ->delete();

        return $this->redirect(route('diagnosticos.show', $diagnostico), navigate: false);
    }

    public function render(): View
    {
        return view('livewire.diagnostico.quiz')->layoutData([
            'title' => 'Diagnóstico — '.($this->empresa->nome_fantasia ?: $this->empresa->razao_social),
        ]);
    }

    /**
     * Regras de validação por bloco. Numéricos aceitam ponto OU vírgula brasileira
     * via regex (string canônica "1234.56" ou "1.234,56"). Inteiros para dias e %.
     *
     * @return array<string, array<int, mixed>|string>
     */
    private function regrasDoBloco(int $bloco): array
    {
        return match ($bloco) {
            1 => [
                'Q01' => ['required', Rule::in(['industria'])],
            ],
            2 => [
                'Q08' => ['required', 'string', $this->regraBrl()],
                'Q09' => ['required', 'string', $this->regraBrl()],
                'Q14' => ['required', 'string', $this->regraBrl()],
                'Q15' => ['required', 'string', $this->regraBrl()],
                'Q16' => ['required', 'string', $this->regraBrl()],
                'Q10' => ['required', 'string', $this->regraDiasInteiro()],
                'Q11' => ['required', 'string', $this->regraDiasInteiro()],
                'Q12' => ['required', 'string', $this->regraDiasInteiro()],
                'Q13' => ['required', 'string', $this->regraPercentual()],
            ],
            3 => [
                'Q02' => ['required', 'string', $this->regraBrl()],
                'Q03' => ['required', 'string', $this->regraBrl()],
                'Q04' => ['required', 'string', $this->regraBrl()],
                'Q05' => ['required', 'string', $this->regraBrl()],
                'Q06' => ['required', 'string', $this->regraBrl()],
                'Q07' => ['required', 'string', $this->regraBrl()],
            ],
            4 => $this->regrasBloco4(),
            default => [],
        };
    }

    /**
     * Bloco 4 tem regras condicionais (Q18..Q23 obrigatórios quando Q17="sim";
     * Q20 < Q09).
     *
     * @return array<string, array<int, mixed>|\Closure>
     */
    private function regrasBloco4(): array
    {
        $regras = [
            'Q17' => ['required', Rule::in(['sim', 'nao'])],
        ];

        if ($this->Q17 === 'sim') {
            $regras['Q18'] = ['required', 'string', $this->regraBrl()];
            $regras['Q19'] = ['required', 'string', $this->regraBrl()];
            $regras['Q20'] = ['required', 'string', $this->regraBrl(), $this->regraQ20MenorQueQ09()];
            $regras['Q21'] = ['required', 'string', $this->regraCpf()];
            $regras['Q22'] = ['required', 'string', $this->regraCpf()];
            $regras['Q23'] = ['required', 'string', $this->regraCpf()];
        } else {
            // Quando Q17="nao", os campos opcionais devem permanecer nulos —
            // garantia de não vazar dado parcial para o motor.
            $regras['Q18'] = ['nullable'];
            $regras['Q19'] = ['nullable'];
            $regras['Q20'] = ['nullable'];
            $regras['Q21'] = ['nullable'];
            $regras['Q22'] = ['nullable'];
            $regras['Q23'] = ['nullable'];
        }

        return $regras;
    }

    /**
     * Aceita "1.234,56" (BR), "1234,56" ou "1234.56" e exige valor >= 0.
     */
    private function regraBrl(): \Closure
    {
        return function (string $attr, mixed $value, \Closure $fail): void {
            $float = self::parseDecimal((string) $value);
            if ($float === null) {
                $fail('Informe um valor numérico válido (ex.: 1.234,56).');

                return;
            }
            if ($float < 0) {
                $fail('O valor deve ser maior ou igual a zero.');
            }
        };
    }

    private function regraDiasInteiro(): \Closure
    {
        return function (string $attr, mixed $value, \Closure $fail): void {
            $digitos = preg_replace('/\D+/', '', (string) $value) ?? '';
            if ($digitos === '' || ! ctype_digit($digitos)) {
                $fail('Informe um número inteiro de dias (≥ 0).');
            }
        };
    }

    private function regraPercentual(): \Closure
    {
        return function (string $attr, mixed $value, \Closure $fail): void {
            $float = self::parseDecimal((string) $value);
            if ($float === null) {
                $fail('Informe um percentual válido (ex.: 5,3).');

                return;
            }
            if ($float < 0 || $float > 100) {
                $fail('O percentual deve estar entre 0 e 100.');
            }
        };
    }

    private function regraCpf(): \Closure
    {
        return function (string $attr, mixed $value, \Closure $fail): void {
            if (! Cpf::valido((string) $value)) {
                $fail('CPF inválido — confira os dígitos.');
            }
        };
    }

    /**
     * Regra do Anexo A: Q20 (venda mensal com cartão) deve ser inferior a Q09
     * (venda mensal total). Bloqueio rígido.
     */
    private function regraQ20MenorQueQ09(): \Closure
    {
        return function (string $attr, mixed $value, \Closure $fail): void {
            $q20 = self::parseDecimal((string) $value);
            $q09 = self::parseDecimal((string) ($this->Q09 ?? ''));

            if ($q20 === null || $q09 === null) {
                return;
            }
            if ($q20 >= $q09) {
                $fail('A venda mensal com cartão/duplicatas deve ser inferior à venda mensal total (Q09).');
            }
        };
    }

    /**
     * Persiste o estado atual do quiz no rascunho. UPSERT por
     * `(usuario_id, empresa_analisada_id)` — UNIQUE parcial cuida do resto.
     *
     * STORY-035 (ADR-004 §Decisão 2): emite `quiz_iniciado` na **primeira**
     * transição `null → rascunho`, síncrono dentro da mesma transação do INSERT
     * (CA-5 — falha do evento faz rollback do rascunho). `wasRecentlyCreated` é
     * verdadeiro só no INSERT, então re-saves (update) não reemitem (CA-1).
     * `quiz_id` é o próprio id do rascunho — chave de correlação reusada por
     * `diagnostico_concluido`.
     */
    private function persistirRascunho(): void
    {
        DB::transaction(function (): void {
            $rascunho = QuizRascunho::query()->updateOrCreate(
                ['empresa_analisada_id' => $this->empresa->id],
                [
                    'usuario_id' => Auth::id(),
                    'quiz_payload' => $this->payloadParcial(),
                    'ultimo_bloco_preenchido' => $this->bloco_atual,
                    'expires_at' => now()->addDays(90),
                ],
            );

            if ($rascunho->wasRecentlyCreated) {
                EventLogger::emit(
                    nomeEvento: 'quiz_iniciado',
                    propriedades: [
                        'quiz_id' => $rascunho->id,
                        'quiz_versao' => (string) config('quiz.versao'),
                    ],
                    usuarioId: (string) Auth::id(),
                    empresaId: $this->empresa->id,
                );
            }
        });
    }

    /**
     * Payload "raw" (formato BR preservado) para o rascunho. Inclui
     * `alertas_aceitos` para que a decisão de Roberto sobreviva à retomada de
     * rascunho (re-hidratado em {@see mount()} via `property_exists`).
     *
     * @return array<string, mixed>
     */
    private function payloadParcial(): array
    {
        return [
            'Q01' => $this->Q01,
            'Q02' => $this->Q02, 'Q03' => $this->Q03, 'Q04' => $this->Q04, 'Q05' => $this->Q05,
            'Q06' => $this->Q06, 'Q07' => $this->Q07, 'Q08' => $this->Q08, 'Q09' => $this->Q09,
            'Q10' => $this->Q10, 'Q11' => $this->Q11, 'Q12' => $this->Q12, 'Q13' => $this->Q13,
            'Q14' => $this->Q14, 'Q15' => $this->Q15, 'Q16' => $this->Q16, 'Q17' => $this->Q17,
            'Q18' => $this->Q18, 'Q19' => $this->Q19, 'Q20' => $this->Q20,
            'Q21' => $this->Q21 !== null ? Cpf::normalizar($this->Q21) : null,
            'Q22' => $this->Q22 !== null ? Cpf::normalizar($this->Q22) : null,
            'Q23' => $this->Q23 !== null ? Cpf::normalizar($this->Q23) : null,
            'alertas_aceitos' => $this->alertas_aceitos,
        ];
    }

    /**
     * Payload canônico para o motor: decimais como string ponto-decimal, inteiros
     * como int, CPFs só dígitos, Q17 booleanizado. Campos opcionais (Q18..Q23)
     * viram null quando Q17="nao".
     *
     * @return array<string, mixed>
     */
    private function payloadCanonico(): array
    {
        $captar = $this->Q17 === 'sim';

        return [
            'Q01' => $this->Q01,
            'Q02' => self::strFloat($this->Q02),
            'Q03' => self::strFloat($this->Q03),
            'Q04' => self::strFloat($this->Q04),
            'Q05' => self::strFloat($this->Q05),
            'Q06' => self::strFloat($this->Q06),
            'Q07' => self::strFloat($this->Q07),
            'Q08' => self::strFloat($this->Q08),
            'Q09' => self::strFloat($this->Q09),
            'Q10' => self::intDigitos($this->Q10),
            'Q11' => self::intDigitos($this->Q11),
            'Q12' => self::intDigitos($this->Q12),
            'Q13' => self::strFloat($this->Q13),
            'Q14' => self::strFloat($this->Q14),
            'Q15' => self::strFloat($this->Q15),
            'Q16' => self::strFloat($this->Q16),
            'Q17' => $captar,
            'Q18' => $captar ? self::strFloat($this->Q18) : null,
            'Q19' => $captar ? self::strFloat($this->Q19) : null,
            'Q20' => $captar ? self::strFloat($this->Q20) : null,
            'Q21' => $captar && $this->Q21 !== null ? Cpf::normalizar($this->Q21) : null,
            'Q22' => $captar && $this->Q22 !== null ? Cpf::normalizar($this->Q22) : null,
            'Q23' => $captar && $this->Q23 !== null ? Cpf::normalizar($this->Q23) : null,
        ];
    }

    /**
     * Converte "1.234,56", "1234,56", "1234.56" e "1234" em float.
     * Retorna null para entrada vazia ou inválida.
     */
    public static function parseDecimal(?string $br): ?float
    {
        if ($br === null) {
            return null;
        }
        $br = trim($br);
        if ($br === '') {
            return null;
        }

        // Remove separadores de milhar e troca vírgula por ponto. Aceita ambos os formatos.
        if (str_contains($br, ',')) {
            $sem = str_replace('.', '', $br);     // BR: pontos são milhar.
            $sem = str_replace(',', '.', $sem);
        } else {
            $sem = $br;
        }

        if (! is_numeric($sem)) {
            return null;
        }

        return (float) $sem;
    }

    /**
     * Converte para string ponto-decimal canônica ("1234.56") preservando precisão
     * decimal sem notação científica. Retorna null se entrada vazia/inválida.
     */
    private static function strFloat(?string $br): ?string
    {
        $float = self::parseDecimal($br);
        if ($float === null) {
            return null;
        }

        // PHP `(string) 1234.56` pode virar "1234.56" mas para certos floats ("0.1+0.2")
        // entrega artefatos. number_format com 2 casas atende todos os casos do Anexo A
        // (R$ e %). Para inteiros (.0) o trailing 0 é aceito pelo motor.
        return number_format($float, 2, '.', '');
    }

    private static function intDigitos(?string $br): ?int
    {
        if ($br === null) {
            return null;
        }
        $digitos = preg_replace('/\D+/', '', $br) ?? '';
        if ($digitos === '') {
            return null;
        }

        return (int) $digitos;
    }
}
