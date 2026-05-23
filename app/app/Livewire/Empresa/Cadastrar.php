<?php

declare(strict_types=1);

namespace App\Livewire\Empresa;

use App\Domain\FonteEnriquecimento;
use App\Domain\SituacaoCadastral;
use App\Domain\TipoDocumento;
use App\Domain\Uf;
use App\Models\EmpresaAnalisada;
use App\Observabilidade\AuditLogger;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Cadastro de Empresa Analisada — caminho manual (STORY-014 CA-2..CA-6).
 *
 * Form Livewire em `/empresas/nova` (autenticado). Cria registro com
 * `fonte_enriquecimento = 'manual'` e redireciona para `/empresas/{id}`.
 *
 * O enriquecimento via API da RFB (STORY-015) vai empilhar em cima deste form
 * — por isso a estrutura já comporta os mesmos campos da resposta da RFB.
 *
 * Audit log `empresa.cadastrada` sem o `documento` (PII — espec V2 §1.5.2 +
 * STORY-014 CA-6). O `documento` vive apenas em `empresas_analisadas` + log
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

        $usuarioId = (string) Auth::id();

        $empresa = DB::transaction(function () use ($validados, $usuarioId): EmpresaAnalisada {
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
                'fonte_enriquecimento' => FonteEnriquecimento::Manual->value,
                'enriquecido_at' => null,
            ]);

            AuditLogger::log(
                action: 'empresa.cadastrada',
                subjectType: 'EmpresaAnalisada',
                subjectId: $e->id,
                actorType: 'user',
                actorId: $usuarioId,
                usuarioId: $usuarioId,
                after: [
                    // CA-6: SEM documento aqui (PII). Vive em `empresas_analisadas`.
                    'tipo_documento' => $e->tipo_documento->value,
                    'fonte_enriquecimento' => $e->fonte_enriquecimento->value,
                    'uf' => $e->uf,
                ],
            );

            return $e;
        });

        // Log estruturado **sem** o documento bruto — documento é PII (LGPD +
        // quality-standards). O LogSanitizer cobre as chaves `cpf`/`cnpj`, mas a
        // coluna canônica aqui é polimórfica (`documento`). Mantemos `tipo_documento`
        // para diagnose e deixamos o valor cru só em `empresas_analisadas`.
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
