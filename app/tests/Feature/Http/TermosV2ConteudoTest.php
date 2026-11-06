<?php

declare(strict_types=1);

use Illuminate\Support\Facades\View;

/**
 * STORY-094 CA-6 (D-11) — os pontos-chave de cada cláusula do Termo v2 e da Política v2 batem com o que o sistema
 * faz. A v2 ainda não foi publicada, então este texto é a v2 mesma (sem v3). Cada trecho aqui é uma regra do código:
 * se ela mudar, o texto muda junto e este teste avisa.
 */
function _texto(string $view): string
{
    $html = View::make($view)->render();

    return (string) preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
}

describe('Termo de Adesão v2', function () {
    beforeEach(fn () => $this->termo = _texto('legal.termo-adesao-v2'));

    it('cl. 4: cota e preço contratados valem até a troca de plano ou o reajuste com aviso de 30 dias; upgrade é proporcional e a renovação devolve a cota cheia', function () {
        expect($this->termo)
            ->toContain('a cota e o preço contratados valem até você trocar de plano ou até um reajuste')
            ->toContain('aviso de 30 dias')
            ->toContain('uma cota extra proporcional ao tempo que falta para a cota voltar cheia')
            ->toContain('A cota volta cheia a cada renovação');
    });

    it('cl. 6: ao fim do desconto a assinatura volta ao preço cheio informado na contratação', function () {
        expect($this->termo)
            ->toContain('volta ao preço cheio informado na contratação')
            ->not->toContain('preço vigente do plano');
    });

    it('cl. 7: o acesso continua durante as retentativas, sem cota não roda análise e o ciclo encerra 48 horas depois da terceira retentativa', function () {
        expect($this->termo)
            ->toContain('a assinatura fica em atraso, mas as funções pagas continuam disponíveis')
            ->toContain('sem cota')
            ->toContain('falta de saldo')
            ->toContain('tentamos cobrar novamente três vezes, a cada 48 horas')
            ->toContain('48 horas depois da terceira tentativa')
            ->toContain('o ciclo é encerrado')
            ->not->toContain('Após a terceira falha');
    });

    it('cl. 7: reajuste só com 30 dias de aviso e só para ciclos novos', function () {
        expect($this->termo)->toContain('Reajustes são comunicados por e-mail com ao menos 30 dias de antecedência');
    });

    it('cl. 8: em atraso cancela a qualquer momento com encerramento imediato; histórico e lançamentos do Raio X ficam, sem limite de 12 meses', function () {
        expect($this->termo)
            ->toContain('assinatura em atraso pode ser cancelada a qualquer momento')
            ->toContain('encerrado imediatamente')
            ->toContain('o ciclo não foi pago')
            ->toContain('histórico de diagnósticos e os lançamentos do Raio X do Negócio continuam disponíveis')
            ->not->toContain('12 meses mais recentes');
    });

    it('cl. 9: o arrependimento vale para planos e para créditos', function () {
        expect($this->termo)
            ->toContain('art. 49 do Código de Defesa do Consumidor')
            ->toContain('plano ou pacote de créditos')
            ->toContain('Meu plano')
            ->toContain('7 dias corridos');
    });

    it('as cláusulas e o aceite seguem numerados de 1 a 16', function () {
        preg_match_all('/<h2>(\d+)\./', View::make('legal.termo-adesao-v2')->render(), $m);

        expect($m[1])->toBe(array_map('strval', range(1, 16)));
    });
});

describe('Política de Privacidade v2', function () {
    beforeEach(fn () => $this->politica = _texto('legal.politica-privacidade-v2'));

    it('§1: guarda bandeira e os 4 últimos dígitos do cartão, nunca o número completo', function () {
        expect($this->politica)
            ->toContain('bandeira e os quatro últimos dígitos do cartão')
            ->toContain('número completo, o código de segurança e a validade ficam apenas com o provedor');
    });

    it('§3: descreve o que o provedor recebe: o checkout não envia CPF, nome nem e-mail; o pagador digita na página do provedor', function () {
        expect($this->politica)
            ->toContain('Asaas')
            ->toContain('a descrição e o valor da cobrança')
            ->toContain('DEFOnline não envia seu nome, CPF ou e-mail ao provedor')
            ->toContain('você os informa diretamente na página de pagamento do provedor')
            ->not->toContain('nome, CPF ou CNPJ, e-mail e dados da cobrança');
    });
});
