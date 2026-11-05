<?php

declare(strict_types=1);

namespace App\Support\Relatorio;

use App\Models\Diagnostico;
use Dompdf\Canvas;
use Dompdf\Dompdf;
use Dompdf\FontMetrics;
use Dompdf\Options;
use Illuminate\Support\Str;

/**
 * PDF do diagnóstico, gerado no próprio processo PHP com dompdf.
 *
 * Escolhido no lugar do Chromium em container (ADR-008 Decisão 4) pelo custo:
 * a imagem do Gotenberg tem ~2,4 GB e o Chromium pede centenas de MB de RAM por
 * conversão numa VPS de 1 vCPU; o dompdf é ~3 MB de vendor e roda na request.
 * O argumento da ADR contra o dompdf (terceira cópia da paleta) não se aplica:
 * o `pdf.css` usa `var(--…)` e {@see TokensDeDesign} resolve a partir do
 * `tokens.css`, a fonte única.
 *
 * Síncrono e sem retenção: o PDF é montado do snapshot e devolvido na resposta,
 * nunca gravado em disco (mesma postura da planilha na ADR-008).
 */
final class PdfDiagnostico
{
    private const PESOS_INTER = [
        300 => 'Inter-Light.ttf',
        400 => 'Inter-Regular.ttf',
        500 => 'Inter-Medium.ttf',
        600 => 'Inter-SemiBold.ttf',
    ];

    public function __construct(private readonly TokensDeDesign $tokens) {}

    public static function padrao(): self
    {
        return new self(TokensDeDesign::doArquivo());
    }

    public function html(Diagnostico $diagnostico): string
    {
        return view('diagnosticos.pdf', [
            'diagnostico' => $diagnostico,
            'css' => $this->css(),
            'logoSvg' => $this->logo(),
        ])->render();
    }

    /** Bytes do PDF. */
    public function gerar(Diagnostico $diagnostico): string
    {
        $dompdf = new Dompdf($this->opcoes());
        $dompdf->loadHtml($this->html($diagnostico), 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $this->numerarPaginas($dompdf, $diagnostico);

        return (string) $dompdf->output();
    }

    public function nomeArquivo(Diagnostico $diagnostico): string
    {
        $empresa = $diagnostico->empresa;
        $nome = Str::slug($empresa->nomeExibicao()) ?: 'empresa';

        return sprintf('diagnostico-%s-%s.pdf', $nome, $diagnostico->gerado_em->format('Y-m-d'));
    }

    private function opcoes(): Options
    {
        $cache = storage_path('framework/cache/dompdf');
        if (! is_dir($cache)) {
            mkdir($cache, 0775, true);
        }

        return new Options([
            'fontDir' => $cache,
            'fontCache' => $cache,
            'tempDir' => sys_get_temp_dir(),
            'chroot' => [resource_path('fonts')],
            'defaultFont' => 'Inter',
            'defaultMediaType' => 'print',
            'isRemoteEnabled' => false,
            'isPhpEnabled' => false,
            'isJavascriptEnabled' => false,
            'isFontSubsettingEnabled' => true,
            'dpi' => 96,
        ]);
    }

    private function css(): string
    {
        $faces = '';
        foreach (self::PESOS_INTER as $peso => $arquivo) {
            $faces .= $this->fontFace('normal', $peso, $arquivo);
        }
        // Itálico só no peso regular — é o único usado (rótulos "Indisponível", notas).
        $faces .= $this->fontFace('italic', 400, 'Inter-Italic.ttf');

        // Sem comentários: eles citam `var(--…)` em prosa e não servem ao dompdf.
        $pdfCss = (string) preg_replace('#/\*.*?\*/#s', '', (string) file_get_contents(resource_path('css/pdf.css')));

        return $faces.$this->tokens->resolver($pdfCss);
    }

    private function fontFace(string $estilo, int $peso, string $arquivo): string
    {
        return sprintf(
            "@font-face { font-family: 'Inter'; font-style: %s; font-weight: %d; src: url('%s') format('truetype'); }\n",
            $estilo,
            $peso,
            resource_path('fonts/inter/'.$arquivo),
        );
    }

    /** Logo do `<x-logo>` como data URI, nas cores dos tokens. */
    private function logo(): string
    {
        $svg = sprintf(
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 32 32" width="32" height="32">'
            .'<path fill="%s" fill-rule="evenodd" d="M4 4 H15 A13 13 0 0 1 15 28 H4 Z M9 9 V23 H15 A9 9 0 0 0 15 9 Z"/>'
            .'<circle cx="26" cy="25" r="2" fill="%s"/></svg>',
            $this->tokens->valor('color-primary'),
            $this->tokens->valor('color-tertiary'),
        );

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }

    /** Rodapé corrido nas páginas de conteúdo — a capa (página 1) fica limpa. */
    private function numerarPaginas(Dompdf $dompdf, Diagnostico $diagnostico): void
    {
        $empresa = $diagnostico->empresa;
        $nomeEmpresa = $empresa->nomeExibicao();
        $data = $diagnostico->gerado_em->format('d/m/Y');
        [$r, $g, $b] = sscanf(ltrim($this->tokens->valor('color-secondary'), '#'), '%2x%2x%2x');
        $cor = [$r / 255, $g / 255, $b / 255];
        [$lr, $lg, $lb] = sscanf(ltrim($this->tokens->valor('color-border'), '#'), '%2x%2x%2x');
        $corLinha = [$lr / 255, $lg / 255, $lb / 255];

        $canvas = $dompdf->getCanvas();
        $canvas->page_script(function (int $pagina, int $total, Canvas $canvas, FontMetrics $metricas) use ($nomeEmpresa, $data, $cor, $corLinha): void {
            if ($pagina === 1) {
                return;
            }
            $fonte = $metricas->getFont('Inter', 'normal');
            $tamanho = 7.5;
            $margem = 17 * 72 / 25.4;   // 17 mm em pt — igual às laterais do @page.
            $y = $canvas->get_height() - 12 * 72 / 25.4;
            $largura = $canvas->get_width();

            $direita = sprintf('Página %d de %d', $pagina, $total);
            $wDireita = $metricas->getTextWidth($direita, $fonte, $tamanho);

            // Nome longo (razão social de autônomo, p.ex.) é encurtado com "…" para
            // nunca encostar na numeração.
            $disponivel = $largura - 2 * $margem - $wDireita - 24;
            $nome = $nomeEmpresa;
            $montar = fn (string $n): string => "DEFOnline · {$n} · Diagnóstico de {$data}";
            while (mb_strlen($nome) > 8 && $metricas->getTextWidth($montar($nome), $fonte, $tamanho) > $disponivel) {
                $nome = rtrim(mb_substr($nome, 0, mb_strlen($nome) - 2)).'…';
                $nome = (string) preg_replace('/…+$/u', '…', $nome);
            }

            $canvas->line($margem, $y - 5, $largura - $margem, $y - 5, $corLinha, 0.5);
            $canvas->text($margem, $y, $montar($nome), $fonte, $tamanho, $cor);
            $canvas->text($largura - $margem - $wDireita, $y, $direita, $fonte, $tamanho, $cor);
        });
    }
}
