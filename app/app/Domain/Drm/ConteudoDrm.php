<?php

declare(strict_types=1);

namespace App\Domain\Drm;

use Illuminate\Support\Facades\Log;

/**
 * Resolvedor do pacote de conteúdo do DRM — `PDR-009` §2, CA-10 da `STORY-052`.
 *
 * **Sobreposição, não mistura.** Toda chave é procurada primeiro no arquivo
 * validado (`config/drm/<arquivo>.php`) e só cai no provisório
 * (`config/drm/provisorio/<arquivo>.php`) se não existir lá. A consequência é
 * a que interessa ao PO: **promover uma entrada é mover a linha de um arquivo
 * para o outro** — nunca editar código de aplicação, nunca abrir estória de
 * engenharia, nunca fazer deploy.
 *
 * **A ordem importa e não é simétrica.** Validado ganha do provisório sempre.
 * Se a mesma chave existir nos dois, o produto exibiria o texto validado
 * enquanto alguém acha que corrigiu o provisório — por isso a duplicata é
 * proibida e reprovada por teste arquitetural, não só desencorajada.
 *
 * **Inventário.** Cada chave servida a partir do provisório é registrada em
 * `drm.lacunas_path`, no mesmo padrão idempotente que `MatrizRecomendacoes`
 * já usa no Diagnóstico. O PO abre um arquivo e vê o que ainda não passou pela
 * EBC, sem ler código.
 *
 * **Determinismo.** `texto()` é puro em relação ao que devolve: lê só config.
 * A escrita no arquivo de lacunas é I/O lateral idempotente e não muda o
 * retorno — o mesmo cuidado que o `IDR-010` exige do motor de Diagnóstico.
 */
final class ConteudoDrm
{
    /** Devolvido quando a chave não existe em lugar nenhum. */
    public const AUSENTE = null;

    /**
     * Texto de tooltip de um campo do lançamento (`D00`..`D42`).
     *
     * Devolve `null` quando não há tooltip — e `null` aqui é uma instrução, não
     * uma falha: **campo sem tooltip não renderiza o `<x-help>`**, nem ícone
     * nem balão vazio (`PDR-009` §1). Um ícone de ajuda que abre um balão
     * genérico gasta o toque do usuário e ensina que a ajuda não ajuda.
     */
    public function help(string $campo): ?string
    {
        return $this->resolver('help', "campos.{$campo}");
    }

    /**
     * Mensagem de bloqueio (`B1`..`B4`) do Anexo K §K.6.
     *
     * @return array<string, string|null>|null
     */
    public function bloqueio(string $codigo): ?array
    {
        return $this->resolver('alertas', "bloqueios.{$codigo}");
    }

    /**
     * Mensagem de alerta (`A1`..`A6`) do Anexo K §K.6.
     *
     * @return array<string, string|null>|null
     */
    public function alerta(string $codigo): ?array
    {
        return $this->resolver('alertas', "alertas.{$codigo}");
    }

    /**
     * Texto de leitura em linguagem simples (`STORY-054`).
     *
     * Devolve `null` enquanto as faixas não forem calibradas, e isso é a
     * decisão, não a consequência de faltar tempo: **onde o farol cala, a
     * leitura cala** (`PDR-009` §1). Não há contrapartida em `provisorio/` —
     * de propósito.
     */
    public function leitura(string $linha, string $farol): ?string
    {
        return $this->resolver('leitura', "por_linha_e_farol.{$linha}.{$farol}");
    }

    /**
     * Rótulo de linha da demonstração, no modo de apresentação pedido.
     *
     * `L4` é a única linha cujo rótulo varia por setor (Anexo K §K.4), e é
     * resolvida aqui em vez de deixar a decisão para cada superfície.
     */
    public function rotuloLinha(string $linha, string $modo, string $setor): ?string
    {
        if ($linha === 'L4') {
            return $this->rotuloGrupoCusto($setor);
        }

        return $this->resolver('rotulos', "linhas.{$modo}.{$linha}");
    }

    public function rotuloCampo(string $campo): ?string
    {
        return $this->resolver('rotulos', "campos.{$campo}");
    }

    /** Rótulo de `vendas`, `administrativas` ou `financeiras` — iguais nos três setores. */
    public function rotuloGrupo(string $grupo): ?string
    {
        return $this->resolver('rotulos', "grupos.{$grupo}");
    }

    public function rotuloGrupoCusto(string $setor): ?string
    {
        return $this->resolver('rotulos', "grupos_por_setor.{$setor}.custo");
    }

    /** Mensagem semântica de um motivo de indisponibilidade (`indisponivel:*`), DDR-005. */
    public function indisponibilidade(string $motivo): ?string
    {
        return $this->resolver('rotulos', "indisponibilidade.{$motivo}");
    }

    /**
     * Nome do módulo na interface — "Raio X do Negócio" (`PDR-009` §3).
     *
     * Vive em conteúdo, não em constante de código, porque nome comercial muda
     * e nome técnico não deve mudar junto: "DRM" segue em tabelas, rotas,
     * classes e versões, sem renomeação.
     */
    public function nomeModulo(): ?string
    {
        return $this->resolver('rotulos', 'modulo.nome');
    }

    /**
     * Núcleo da sobreposição.
     *
     * @return mixed O valor validado, o provisório, ou `null`.
     */
    private function resolver(string $arquivo, string $caminho): mixed
    {
        $validado = config("drm.{$arquivo}.{$caminho}");
        if ($validado !== null) {
            return $validado;
        }

        $provisorio = config("drm.provisorio.{$arquivo}.{$caminho}");
        if ($provisorio !== null) {
            $this->registrarLacuna($arquivo, $caminho);

            return $provisorio;
        }

        return self::AUSENTE;
    }

    private function registrarLacuna(string $arquivo, string $caminho): void
    {
        Log::debug('drm.conteudo.provisorio', ['arquivo' => $arquivo, 'chave' => $caminho]);

        $destino = (string) config('drm.lacunas_path', '');
        if ($destino === '' || ! is_dir(dirname($destino))) {
            return;
        }

        $linha = sprintf('- `%s` → `%s`', $arquivo, $caminho);

        $existente = is_file($destino) ? (string) @file_get_contents($destino) : '';
        if (str_contains($existente, $linha."\n")) {
            return;
        }

        if ($existente === '') {
            $existente = "# Conteúdo do DRM servido a partir de `provisorio/`\n\n".
                "Chaves que o produto exibiu em runtime **sem validação da EBC**.\n".
                "Auto-append por `App\\Domain\\Drm\\ConteudoDrm` (idempotente).\n\n".
                "Promover uma entrada: mover a linha do arquivo em `config/drm/provisorio/`\n".
                "para o homônimo em `config/drm/`, subir os dois `version` e o\n".
                "`conteudo_version` de `config/drm.php`. Ver o README daquele diretório.\n\n";
        }

        @file_put_contents($destino, $existente.$linha."\n");
    }
}
