/*
 * Máscaras de entrada de dinheiro e percentual (requisito RNF-UI-01). As telas não usam estas funções
 * direto: usam <x-input-moeda> e <x-input-percentual>, que ligam a diretiva `x-mascara`.
 *
 * Reescrever `el.value` não gera evento `input`, então a diretiva o reemite (BUG-001): senão o
 * `wire:model` guardaria o texto de antes da formatação. A reemissão termina em dois passes, porque
 * no segundo o valor já está formatado.
 */
const MAX_DIGITOS_MOEDA = 15;

/** Digitação a partir dos centavos: 5 vira 0,05 e 150000 vira 1.500,00. */
export function mascararMoeda(texto) {
    const digitos = texto.replace(/\D/g, '');

    if (digitos === '') {
        return '';
    }

    const centavos = digitos.replace(/^0+/, '').slice(0, MAX_DIGITOS_MOEDA).padStart(3, '0');
    const inteiro = centavos.slice(0, -2).replace(/^0+(?=\d)/, '').replace(/\B(?=(\d{3})+(?!\d))/g, '.');

    return `${inteiro},${centavos.slice(-2)}`;
}

/**
 * Digitação natural: "3,46" fica "3,46" e "100" fica "100". Não limita a 100, porque corrigir sozinho
 * mudaria o que a pessoa digitou (150 viraria um cupom de 100%): quem recusa é a validação do servidor.
 */
export function mascararPercentual(texto, decimais = 2) {
    if (decimais === 0) {
        return texto.replace(/\D/g, '').replace(/^0+(?=\d)/, '').slice(0, 3);
    }

    const limpo = texto.replace(/\./g, ',').replace(/[^\d,]/g, '');
    const virgula = limpo.indexOf(',');

    if (virgula === -1) {
        return limpo.replace(/^0+(?=\d)/, '').slice(0, 3);
    }

    const inteiro = limpo.slice(0, virgula).replace(/^0+(?=\d)/, '').slice(0, 3) || '0';

    return `${inteiro},${limpo.slice(virgula + 1).replace(/,/g, '').slice(0, decimais)}`;
}

export function registrarMascaras(Alpine) {
    // x-mascara:moeda, x-mascara:percentual e x-mascara:percentual="0" (casas decimais).
    Alpine.directive('mascara', (el, { value, expression }) => {
        const mascarar = value === 'moeda'
            ? mascararMoeda
            : (texto) => mascararPercentual(texto, expression === '' ? 2 : Number(expression));

        el.addEventListener('input', () => {
            const formatado = mascarar(el.value);

            if (el.value !== formatado) {
                el.value = formatado;
                el.dispatchEvent(new Event('input'));
            }
        });
    });
}
