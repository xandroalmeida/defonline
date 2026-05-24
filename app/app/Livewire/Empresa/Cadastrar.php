<?php

declare(strict_types=1);

namespace App\Livewire\Empresa;

use App\Domain\Cnpj;
use App\Domain\FonteEnriquecimento;
use App\Domain\SituacaoCadastral;
use App\Domain\TipoDocumento;
use App\Domain\Uf;
use App\Models\EmpresaAnalisada;
use App\Observabilidade\AuditLogger;
use App\Observabilidade\EventLogger;
use App\Services\Rfb\RfbCnpjFalhouException;
use App\Services\Rfb\RfbConsultarCnpj;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Cadastro de Empresa Analisada — caminho manual (STORY-014) + enriquecimento
 * via RFB com fallback transparente (STORY-015).
 *
 * Form Livewire em `/empresas/nova` (autenticado). Cria registro com
 * `fonte_enriquecimento = 'manual'` (sem botão) ou `= 'rfb'` (depois do botão
 * "Consultar Receita") e redireciona para `/empresas/{id}`.
 *
 * O caminho RFB delega o trabalho de cache+métrica+audit ao
 * {@see RfbConsultarCnpj}. Falha tipada vira aviso amarelo + form em branco
 * (espec V2 §3.3, NRF §3.1).
 *
 * Audit log `empresa.cadastrada` sem `documento` (PII — espec V2 §1.5.2,
 * STORY-014 CA-6); `documento` vive apenas em `empresas_analisadas` + log
 * estruturado mascarado pelo LogSanitizer.
 */
#[Layout('layouts.app')]
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

    /** Verdadeiro depois de uma consulta RFB bem-sucedida; cai para falso ao editar o CNPJ. */
    public bool $enriquecido = false;

    /** Carimbo do `consultado_at` do provedor RFB, em ISO 8601; vira `enriquecido_at` no submit. */
    public ?string $enriquecidoAt = null;

    /** Mensagem amarela do CA-2 quando a consulta RFB falha (qualquer status). */
    public ?string $mensagemFallback = null;

    /** Status determinístico da última consulta RFB — apoio para testes/Dusk. */
    public ?string $statusConsultaRfb = null;

    public function consultarReceita(RfbConsultarCnpj $consultar): void
    {
        $this->mensagemFallback = null;
        $this->statusConsultaRfb = null;

        // CA-2: botão só faz sentido para CNPJ. Defesa em camadas — o Blade
        // também esconde quando tipo=CPF, mas se chegou aqui no CPF é no-op.
        if ($this->tipo_documento !== TipoDocumento::Cnpj->value) {
            return;
        }

        $digitos = Cnpj::normalizar($this->documento);

        if (! Cnpj::valido($digitos)) {
            $this->limparEnriquecimentoDoForm();
            $this->mensagemFallback = 'Informe um CNPJ válido para consultar a Receita.';
            $this->statusConsultaRfb = 'cnpj_invalido';

            return;
        }

        try {
            $resultado = $consultar->executar($digitos);
        } catch (RfbCnpjFalhouException $falha) {
            $this->limparEnriquecimentoDoForm();
            // Mensagem literal do CA-2 — não personalizar para evitar exposição de
            // detalhes do erro do provedor (status interno só viaja em
            // statusConsultaRfb para fins de teste/audit).
            $this->mensagemFallback = 'Não conseguimos consultar a Receita agora — preencha os campos manualmente.';
            $this->statusConsultaRfb = $falha->status->value;

            return;
        }

        $dados = $resultado->paraFormulario();
        $this->razao_social = $dados['razao_social'];
        $this->nome_fantasia = $dados['nome_fantasia'];
        $this->cnae = $dados['cnae'];
        $this->municipio = $dados['municipio'];
        $this->uf = $dados['uf'];
        $this->situacao_cadastral = $dados['situacao_cadastral'];
        $this->data_fundacao = $dados['data_fundacao'];

        $this->enriquecido = true;
        $this->enriquecidoAt = $resultado->consultadoAt->toIso8601String();
        $this->statusConsultaRfb = 'sucesso';
    }

    public function updatedDocumento(): void
    {
        $this->limparEnriquecimentoCompleto();
    }

    public function updatedTipoDocumento(): void
    {
        $this->limparEnriquecimentoCompleto();
    }

    public function submit(): mixed
    {
        $tipo = TipoDocumento::tryFrom($this->tipo_documento) ?? TipoDocumento::Cnpj;

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
                        $fail($tipo === TipoDocumento::Cnpj
                            ? 'Informe um CNPJ válido.'
                            : 'Informe um CPF válido.');
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
        ], [
            'documento.required' => 'Informe o documento.',
            'documento.size' => $tipo === TipoDocumento::Cnpj
                ? 'O CNPJ deve ter 14 dígitos.'
                : 'O CPF deve ter 11 dígitos.',
            'documento.unique' => 'Você já cadastrou uma empresa com este documento.',
            'razao_social.required' => 'Informe a razão social.',
            'razao_social.min' => 'A razão social deve ter pelo menos 2 caracteres.',
            'cnae.regex' => 'O CNAE deve ter 7 dígitos (somente números).',
            'municipio.required' => 'Informe o município.',
            'uf.required' => 'Informe a UF.',
            'uf.in' => 'Informe uma UF válida.',
            'data_fundacao.before_or_equal' => 'A data de fundação não pode ser no futuro.',
        ])->validate();

        // CA-3: fonte vira `rfb` se houve consulta bem-sucedida E o CNPJ não foi
        // editado depois (updatedDocumento limpa a flag). CPF nunca passa por RFB.
        $fonteRfb = $this->enriquecido && $tipo === TipoDocumento::Cnpj;
        $fonte = $fonteRfb ? FonteEnriquecimento::Rfb : FonteEnriquecimento::Manual;
        $enriquecidoAt = $fonteRfb && $this->enriquecidoAt !== null
            ? Carbon::parse($this->enriquecidoAt)
            : null;

        $usuarioId = (string) Auth::id();

        $empresa = DB::transaction(function () use ($validados, $usuarioId, $fonte, $enriquecidoAt): EmpresaAnalisada {
            $e = EmpresaAnalisada::create([
                'id' => (string) Str::uuid7(),
                'usuario_id' => $usuarioId,
                'tipo_documento' => $validados['tipo_documento'],
                'documento' => $validados['documento'],
                'razao_social' => $validados['razao_social'],
                'nome_fantasia' => $validados['nome_fantasia'],
                'cnae' => $validados['cnae'],
                'municipio' => $validados['municipio'],
                'uf' => $validados['uf'],
                'situacao_cadastral' => $validados['situacao_cadastral'],
                'data_fundacao' => $validados['data_fundacao'],
                'fonte_enriquecimento' => $fonte->value,
                'enriquecido_at' => $enriquecidoAt,
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

        return $this->redirect("/empresas/{$empresa->id}", navigate: false);
    }

    private function limparEnriquecimentoCompleto(): void
    {
        $this->limparEnriquecimentoDoForm();
        $this->mensagemFallback = null;
        $this->statusConsultaRfb = null;
    }

    private function limparEnriquecimentoDoForm(): void
    {
        $this->enriquecido = false;
        $this->enriquecidoAt = null;
    }

    private static function normalizarCnae(string $cnae): ?string
    {
        $digitos = preg_replace('/\D+/', '', $cnae) ?? '';

        return $digitos === '' ? null : $digitos;
    }

    public function render(): View
    {
        return view('livewire.empresa.cadastrar', [
            'ufs' => Uf::cases(),
            'situacoes' => SituacaoCadastral::cases(),
            'tipos' => TipoDocumento::cases(),
        ]);
    }
}
