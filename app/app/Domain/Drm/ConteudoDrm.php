<?php

declare(strict_types=1);

namespace App\Domain\Drm;

use App\Support\InventarioLacunas;
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
     * Frase-modelo da leitura que descreve um fato do mês (`leitura_frases.php`, STORY-054).
     *
     * Diferente de {@see leitura()}, que é interpretação de farol e pode faltar, estas frases são o
     * esqueleto da prosa e **precisam existir**: chave ausente é erro de configuração, nunca texto
     * improvisado (CA-3, leitura nunca generativa).
     */
    public function fraseDeLeitura(string $caminho): string
    {
        $frase = config("drm.leitura_frases.{$caminho}");
        if (! is_string($frase) || $frase === '') {
            throw new \LogicException("Frase de leitura ausente em drm/leitura_frases.php: {$caminho}.");
        }

        return $frase;
    }

    /**
     * Registra no inventário do PO que uma combinação de linha e farol apareceu sem texto de leitura
     * (CA-7). Mesmo arquivo e mesmo padrão idempotente das chaves servidas do provisório.
     */
    public function registrarLacunaDeLeitura(string $linha, string $farol): void
    {
        Log::debug('drm.conteudo.leitura_sem_texto', ['linha' => $linha, 'farol' => $farol]);

        $this->registrarNoInventario(sprintf('- `leitura` → `por_linha_e_farol.%s.%s` — **sem texto**; a tela exibiu a mensagem neutra de reserva', $linha, $farol));
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

    /**
     * Nome de cada um dos 6 blocos do lançamento (a série diz "você parou em {bloco}").
     *
     * @return array<int, string>
     */
    public function nomesDosBlocos(string $setor): array
    {
        return [
            1 => 'O mês e o que entrou',
            2 => (string) $this->rotuloGrupoCusto($setor),
            3 => (string) $this->rotuloGrupo('vendas'),
            4 => (string) $this->rotuloGrupo('administrativas'),
            5 => 'Sua retirada e as despesas financeiras',
            6 => 'Conferir e fechar',
        ];
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

        $this->registrarNoInventario(sprintf('- `%s` → `%s`', $arquivo, $caminho));
    }

    private function registrarNoInventario(string $linha): void
    {
        InventarioLacunas::registrar(
            (string) config('drm.lacunas_path', ''),
            "# Conteúdo do DRM servido a partir de `provisorio/`\n\n".
            "Chaves que o produto exibiu em runtime **sem validação da EBC**.\n".
            "Auto-append por `App\\Domain\\Drm\\ConteudoDrm` (idempotente).\n\n".
            "Promover uma entrada: mover a linha do arquivo em `config/drm/provisorio/`\n".
            "para o homônimo em `config/drm/`, subir os dois `version` e o\n".
            "`conteudo_version` de `config/drm.php`. Ver o README daquele diretório.\n\n",
            $linha,
        );
    }
}
