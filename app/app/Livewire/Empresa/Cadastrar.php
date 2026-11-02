<?php

declare(strict_types=1);

namespace App\Livewire\Empresa;

use App\Domain\Cnpj;
use App\Domain\FonteEnriquecimento;
use App\Domain\Rfb\RfbCnpjStatus;
use App\Domain\SituacaoCadastral;
use App\Domain\TipoDocumento;
use App\Domain\Uf;
use App\Models\EmpresaAnalisada;
use App\Observabilidade\AuditLogger;
use App\Observabilidade\EventLogger;
use App\Services\Rfb\RfbCnpjFalhouException;
use App\Services\Rfb\RfbCnpjResultSerializer;
use App\Services\Rfb\RfbConsultarCnpj;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Cadastro de Empresa Analisada em `/empresas/nova` (autenticado).
 *
 * Dois caminhos (PDR-010):
 *
 * - **CNPJ (default, STORY-063).** O usuário informa só o CNPJ; a consulta à
 *   RFB traz a ficha completa, exibida só-leitura. O resultado fica em
 *   propriedades `#[Locked]` — o cliente não consegue forjar nem alterar dado
 *   da Receita. "Confirmar" grava com `fonte_enriquecimento = 'rfb'`. Falha
 *   da consulta bloqueia o cadastro (sem fallback manual).
 * - **CPF (autônomo, STORY-014).** Formulário manual editável, como antes.
 *
 * O caminho RFB delega cache+métrica+audit ao {@see RfbConsultarCnpj}.
 *
 * Audit log `empresa.cadastrada` sem `documento` (PII — espec V2 §1.5.2,
 * STORY-014 CA-6); `documento` vive apenas em `empresas_analisadas` + log
 * estruturado mascarado pelo LogSanitizer.
 */
#[Layout('components.layouts.app')]
final class Cadastrar extends Component
{
    public string $tipo_documento = TipoDocumento::Cnpj->value;

    public string $documento = '';

    public string $razao_social = '';

    public string $nome_fantasia = '';

    public string $cnae = '';

    public string $municipio = '';

    public string $uf = '';

    public string $situacao_cadastral = SituacaoCadastral::NaoInformada->value;

    public string $data_fundacao = '';

    public const MSG_RECEITA_INDISPONIVEL = 'Não conseguimos consultar a Receita agora. Tente novamente em alguns minutos.';

    public const MSG_CNPJ_INEXISTENTE = 'Não encontramos este CNPJ na Receita. Confira os números.';

    public const MSG_CNPJ_INVALIDO = 'Informe um CNPJ válido.';

    public const MSG_JA_CADASTRADA = 'Você já cadastrou esta empresa.';

    public const OBSERVACOES_MAX = 2000;

    /**
     * Resultado serializado da última consulta RFB bem-sucedida. Locked: só o
     * servidor escreve (STORY-063 CA-4).
     *
     * @var array<string, mixed>|null
     */
    #[Locked]
    public ?array $resultadoRfb = null;

    /** CNPJ (14 dígitos) a que o {@see $resultadoRfb} se refere. */
    #[Locked]
    public ?string $cnpjConsultado = null;

    /** Observações livres do usuário (STORY-063 CA-5). */
    public string $observacoes = '';

    /** Mensagem de erro da consulta (CNPJ inválido, inexistente, Receita fora). */
    public ?string $mensagemErro = null;

    /** Empresa do próprio usuário com o mesmo CNPJ (CA-2) — vira link na mensagem. */
    #[Locked]
    public ?string $empresaExistenteId = null;

    /** Status determinístico da última consulta RFB — apoio para testes/Dusk. */
    #[Locked]
    public ?string $statusConsultaRfb = null;

    public function consultarReceita(RfbConsultarCnpj $consultar): void
    {
        $this->limparConsulta();

        if ($this->tipo_documento !== TipoDocumento::Cnpj->value) {
            return;
        }

        $digitos = Cnpj::normalizar($this->documento);

        if (! Cnpj::valido($digitos)) {
            $this->mensagemErro = self::MSG_CNPJ_INVALIDO;
            $this->statusConsultaRfb = 'cnpj_invalido';

            return;
        }

        if ($this->bloquearSeJaCadastrada($digitos)) {
            return;
        }

        try {
            $resultado = $consultar->executar($digitos);
        } catch (RfbCnpjFalhouException $falha) {
            $this->mensagemErro = $falha->status === RfbCnpjStatus::CnpjInexistente
                ? self::MSG_CNPJ_INEXISTENTE
                : self::MSG_RECEITA_INDISPONIVEL;
            $this->statusConsultaRfb = $falha->status->value;

            return;
        }

        $this->resultadoRfb = RfbCnpjResultSerializer::toArray($resultado);
        $this->cnpjConsultado = $digitos;
        $this->statusConsultaRfb = 'sucesso';
    }

    /**
     * Grava a empresa a partir da ficha RFB consultada (STORY-063 CA-6). Nada
     * do que o usuário digitou entra, exceto as observações.
     */
    public function confirmar(): mixed
    {
        if ($this->resultadoRfb === null || $this->cnpjConsultado === null) {
            $this->mensagemErro = 'Consulte o CNPJ na Receita antes de confirmar.';

            return null;
        }

        $this->validate(
            ['observacoes' => ['nullable', 'string', 'max:'.self::OBSERVACOES_MAX]],
            ['observacoes.max' => 'As observações podem ter até '.self::OBSERVACOES_MAX.' caracteres.'],
        );

        if ($this->bloquearSeJaCadastrada($this->cnpjConsultado)) {
            return null;
        }

        $r = RfbCnpjResultSerializer::fromArray($this->resultadoRfb);

        $empresa = $this->criarEmpresa([
            'tipo_documento' => TipoDocumento::Cnpj->value,
            'documento' => $this->cnpjConsultado,
            'razao_social' => $r->razaoSocial,
            'nome_fantasia' => $r->nomeFantasia,
            'cnae' => $r->cnae,
            'municipio' => $r->municipio,
            'uf' => $r->uf,
            'situacao_cadastral' => $r->situacaoCadastral->value,
            'data_fundacao' => $r->dataFundacao?->format('Y-m-d'),
            'fonte_enriquecimento' => FonteEnriquecimento::Rfb->value,
            'enriquecido_at' => $r->consultadoAt,
            'ficha_rfb' => $r->ficha->toArray(),
            'observacoes' => self::observacoesOuNull($this->observacoes),
        ]);

        return $this->redirect("/empresas/{$empresa->id}", navigate: false);
    }

    public function usarCpf(): void
    {
        $this->tipo_documento = TipoDocumento::Cpf->value;
        $this->updatedTipoDocumento();
    }

    public function usarCnpj(): void
    {
        $this->tipo_documento = TipoDocumento::Cnpj->value;
        $this->updatedTipoDocumento();
    }

    public function updatedDocumento(): void
    {
        $this->limparConsulta();
    }

    public function updatedTipoDocumento(): void
    {
        if (TipoDocumento::tryFrom($this->tipo_documento) === null) {
            $this->tipo_documento = TipoDocumento::Cnpj->value;
        }
        $this->documento = '';
        $this->resetErrorBag();
        $this->limparConsulta();
    }

    /**
     * Cadastro manual — exclusivo do autônomo com CPF (PDR-010 item 6). CNPJ
     * só entra pela ficha da Receita ({@see confirmar()}).
     */
    public function submit(): mixed
    {
        if ($this->tipo_documento !== TipoDocumento::Cpf->value) {
            return $this->confirmar();
        }

        $tipo = TipoDocumento::Cpf;

        // Normaliza o documento e o CNAE em uma fronteira só — UI mantém formato
        // mascarado, persistência fica em dígitos puros (mesma estratégia da
        // STORY-011 com CPF).
        $dados = [
            'tipo_documento' => $tipo->value,
            'documento' => $tipo->normalizar($this->documento),
            'razao_social' => trim($this->razao_social),
            'nome_fantasia' => trim($this->nome_fantasia) === '' ? null : trim($this->nome_fantasia),
            'cnae' => self::normalizarCnae($this->cnae),
            'municipio' => trim($this->municipio),
            'uf' => strtoupper(trim($this->uf)),
            'situacao_cadastral' => $this->situacao_cadastral,
            'data_fundacao' => trim($this->data_fundacao) === '' ? null : trim($this->data_fundacao),
            'observacoes' => $this->observacoes,
        ];

        $tamanhoDocumento = $tipo->tamanho();

        $validados = Validator::make($dados, [
            'tipo_documento' => ['required', Rule::in(array_map(static fn (TipoDocumento $t) => $t->value, TipoDocumento::cases()))],
            'documento' => [
                'required',
                'string',
                "size:{$tamanhoDocumento}",
                function (string $attr, mixed $valor, \Closure $fail) use ($tipo): void {
                    if (! is_string($valor) || ! $tipo->validar($valor)) {
                        $fail('Informe um CPF válido.');
                    }
                },
                // Unique por (usuario_id, documento) — ignora soft-delete.
                Rule::unique('empresas_analisadas', 'documento')
                    ->where(fn ($q) => $q->where('usuario_id', Auth::id())->whereNull('deleted_at')),
            ],
            'razao_social' => ['required', 'string', 'min:2', 'max:255'],
            'nome_fantasia' => ['nullable', 'string', 'max:255'],
            'cnae' => ['nullable', 'string', 'regex:/^\d{7}$/'],
            'municipio' => ['required', 'string', 'max:120'],
            'uf' => ['required', 'string', Rule::in(Uf::valores())],
            'situacao_cadastral' => ['required', Rule::in(array_map(static fn (SituacaoCadastral $s) => $s->value, SituacaoCadastral::cases()))],
            'data_fundacao' => ['nullable', 'date', 'before_or_equal:today'],
            'observacoes' => ['nullable', 'string', 'max:'.self::OBSERVACOES_MAX],
        ], [
            'documento.required' => 'Informe o documento.',
            'documento.size' => 'O CPF deve ter 11 dígitos.',
            'documento.unique' => 'Você já cadastrou uma empresa com este documento.',
            'razao_social.required' => 'Informe a razão social.',
            'razao_social.min' => 'A razão social deve ter pelo menos 2 caracteres.',
            'cnae.regex' => 'O CNAE deve ter 7 dígitos (somente números).',
            'municipio.required' => 'Informe o município.',
            'uf.required' => 'Informe a UF.',
            'uf.in' => 'Informe uma UF válida.',
            'data_fundacao.before_or_equal' => 'A data de fundação não pode ser no futuro.',
            'observacoes.max' => 'As observações podem ter até '.self::OBSERVACOES_MAX.' caracteres.',
        ])->validate();

        $empresa = $this->criarEmpresa([
            ...$validados,
            'fonte_enriquecimento' => FonteEnriquecimento::Manual->value,
            'enriquecido_at' => null,
            'observacoes' => self::observacoesOuNull($this->observacoes),
        ]);

        return $this->redirect("/empresas/{$empresa->id}", navigate: false);
    }

    /**
     * Persistência comum aos dois caminhos: transação + audit + evento
     * `empresa_cadastrada` (STORY-016) + log estruturado. Sem PII.
     *
     * @param  array<string, mixed>  $atributos
     */
    private function criarEmpresa(array $atributos): EmpresaAnalisada
    {
        $usuarioId = (string) Auth::id();

        $empresa = DB::transaction(function () use ($atributos, $usuarioId): EmpresaAnalisada {
            $e = EmpresaAnalisada::create([
                ...$atributos,
                'id' => (string) Str::uuid7(),
                'usuario_id' => $usuarioId,
            ]);

            AuditLogger::log(
                action: 'empresa.cadastrada',
                subjectType: 'EmpresaAnalisada',
                subjectId: $e->id,
                actorType: 'user',
                actorId: $usuarioId,
                usuarioId: $usuarioId,
                after: [
                    // CA-6 STORY-014: SEM documento aqui (PII). Vive em `empresas_analisadas`.
                    'tipo_documento' => $e->tipo_documento->value,
                    'fonte_enriquecimento' => $e->fonte_enriquecimento->value,
                    'uf' => $e->uf,
                ],
            );

            // STORY-016 CA-4 — evento de produto `empresa_cadastrada`. Emitido
            // dentro da mesma transação (ADR-004 §Decisão 2 — atomicidade).
            // Propriedades sem PII: documento NUNCA viaja no payload; CNAE é
            // truncado para 2 dígitos = setor agregado (decisão PO 2026-05-22).
            EventLogger::emit(
                nomeEvento: 'empresa_cadastrada',
                propriedades: [
                    'empresa_id' => $e->id,
                    'tipo_documento' => $e->tipo_documento->value,
                    'fonte_enriquecimento' => $e->fonte_enriquecimento->value,
                    'uf' => $e->uf,
                    'cnae_2digitos' => $e->cnae !== null && strlen($e->cnae) >= 2
                        ? substr($e->cnae, 0, 2)
                        : null,
                ],
                usuarioId: $usuarioId,
                empresaId: $e->id,
            );

            return $e;
        });

        Log::info('empresa.cadastrada', [
            'empresa_id' => $empresa->id,
            'usuario_id' => $usuarioId,
            'tipo_documento' => $empresa->tipo_documento->value,
            'fonte_enriquecimento' => $empresa->fonte_enriquecimento->value,
            'module' => 'empresa',
            'action' => 'cadastrar',
        ]);

        return $empresa;
    }

    private function bloquearSeJaCadastrada(string $cnpj): bool
    {
        $existente = EmpresaAnalisada::query()->where('documento', $cnpj)->value('id');
        if ($existente === null) {
            return false;
        }

        $this->limparConsulta();
        $this->mensagemErro = self::MSG_JA_CADASTRADA;
        $this->empresaExistenteId = (string) $existente;
        $this->statusConsultaRfb = 'ja_cadastrada';

        return true;
    }

    private function limparConsulta(): void
    {
        $this->resultadoRfb = null;
        $this->cnpjConsultado = null;
        $this->mensagemErro = null;
        $this->empresaExistenteId = null;
        $this->statusConsultaRfb = null;
    }

    private static function observacoesOuNull(string $texto): ?string
    {
        $texto = trim($texto);

        return $texto === '' ? null : $texto;
    }

    private static function normalizarCnae(string $cnae): ?string
    {
        $digitos = preg_replace('/\D+/', '', $cnae) ?? '';

        return $digitos === '' ? null : $digitos;
    }

    public function render(): View
    {
        return view('livewire.empresa.cadastrar', [
            'resultado' => $this->resultadoRfb !== null ? RfbCnpjResultSerializer::fromArray($this->resultadoRfb) : null,
            'ufs' => Uf::cases(),
            'situacoes' => SituacaoCadastral::cases(),
            'tipos' => TipoDocumento::cases(),
        ])->layoutData([
            'title' => 'Cadastrar empresa',
            'breadcrumb' => [
                ['label' => 'Minhas Empresas', 'url' => route('home')],
                ['label' => 'Nova empresa', 'url' => route('empresas.nova')],
            ],
        ]);
    }
}
