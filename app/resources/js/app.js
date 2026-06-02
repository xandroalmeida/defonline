/*
 * STORY-040 — Cards de destaque do Resumo Executivo clicáveis.
 *
 * Progressive enhancement: o markup já funciona sem JS — cada destaque é um
 * <a href="#indicador-{codigo}"> que faz o jump nativo (suave via
 * `scroll-behavior: smooth`, com highlight via `:target` na <tr> desktop).
 *
 * Com JS, melhoramos dois pontos:
 *  - Escolhemos o alvo VISÍVEL no breakpoint atual — a tabela (<tr>) em ≥ md ou o
 *    card (<article>) em < md. Os dois layouts coexistem no DOM, então um id único
 *    não cobriria ambos; por isso o id canônico vive só na <tr> e aqui resolvemos
 *    o alvo por `data-codigo` dentro de `#indicadores-detalhados` (escopo que
 *    exclui os <li> do próprio resumo, que também carregam `data-codigo`).
 *  - Aplicamos o highlight de ~1s via classe `.is-highlight`, que reinicia a cada
 *    clique (inclusive cliques repetidos no mesmo destaque).
 */
const DURACAO_HIGHLIGHT_MS = 1000;

function ativarDestaquesResumoExecutivo() {
    const escopo = document.getElementById('indicadores-detalhados');
    const links = document.querySelectorAll('[data-indicador-link]');

    if (!escopo || links.length === 0) {
        return;
    }

    links.forEach((link) => {
        link.addEventListener('click', (evento) => {
            const codigo = link.dataset.indicadorLink;
            const candidatos = escopo.querySelectorAll(`[data-codigo="${codigo}"]`);
            // Alvo visível no breakpoint atual: display:none → getClientRects() vazio.
            const alvo = Array.from(candidatos).find((el) => el.getClientRects().length > 0);

            if (!alvo) {
                return; // sem alvo visível → deixa o jump nativo do href agir
            }

            evento.preventDefault();

            alvo.classList.remove('is-highlight');
            void alvo.offsetWidth; // força reflow p/ reiniciar a animação em cliques repetidos
            alvo.scrollIntoView({ behavior: 'smooth', block: 'start' });
            alvo.classList.add('is-highlight');

            window.setTimeout(() => alvo.classList.remove('is-highlight'), DURACAO_HIGHLIGHT_MS);
        });
    });
}

document.addEventListener('DOMContentLoaded', ativarDestaquesResumoExecutivo);
