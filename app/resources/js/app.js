import { registrarMascaras } from './mascaras';

/*
 * Widget de seleção de CNAE (combobox com busca).
 *
 * - Lazy load: a lista (~1,3 mil subclasses do IBGE) só é baixada de `url` na
 *   primeira vez que o usuário abre o campo; o navegador cacheia (ETag).
 * - Filtro por código (com ou sem pontuação: "1622", "1622-6/99") ou por nome
 *   (todas as palavras, sem diferenciar acento/caixa).
 * - Render incremental: mostra LOTE itens e acrescenta mais ao rolar a lista.
 * - `valor` é entangled com a propriedade Livewire (7 dígitos ou '').
 */
const CNAE_LOTE = 50;
let cnaeCache = null; // Promise compartilhada entre widgets da página

const semAcento = (s) => s.normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase();
const formatarCnae = (c) => `${c.slice(0, 4)}-${c.slice(4, 5)}/${c.slice(5)}`;

function carregarCnaes(url) {
    if (!cnaeCache) {
        cnaeCache = fetch(url, { headers: { Accept: 'application/json' }, credentials: 'same-origin' })
            .then((r) => {
                if (!r.ok) throw new Error(`HTTP ${r.status}`);
                return r.json();
            })
            .then((linhas) => linhas.map((l) => ({
                ...l,
                formatado: formatarCnae(l.codigo),
                busca: semAcento(l.descricao),
            })))
            .catch((e) => {
                cnaeCache = null; // permite nova tentativa
                throw e;
            });
    }
    return cnaeCache;
}

document.addEventListener('alpine:init', () => {
    registrarMascaras(window.Alpine);

    window.Alpine.data('cnaeSelect', ({ valor, url, rotuloInicial = '' }) => ({
        valor,
        url,
        itens: null,
        carregando: false,
        erro: false,
        aberto: false,
        termo: '',
        rotulo: rotuloInicial,
        limite: CNAE_LOTE,
        ativo: 0,

        init() {
            this.$watch('valor', (v) => {
                if (!v) { this.rotulo = ''; return; }
                const item = this.itens?.find((i) => i.codigo === v);
                if (item) this.rotulo = `${item.formatado} — ${item.descricao}`;
            });
        },

        get filtrados() {
            if (!this.itens) return [];
            const t = this.termo.trim();
            if (t === '') return this.itens;
            const digitos = t.replace(/\D/g, '');
            const soCodigo = /^[\d\s./-]+$/.test(t);
            if (soCodigo) return this.itens.filter((i) => i.codigo.startsWith(digitos));
            const palavras = semAcento(t).split(/\s+/).filter(Boolean);
            return this.itens.filter((i) => palavras.every((p) => i.busca.includes(p))
                || (digitos.length >= 2 && i.codigo.startsWith(digitos)));
        },

        get visiveis() {
            return this.filtrados.slice(0, this.limite);
        },

        async abrir() {
            if (this.aberto) return;
            this.aberto = true;
            this.termo = '';
            this.limite = CNAE_LOTE;
            if (this.itens) {
                this.ativo = Math.max(0, this.itens.findIndex((i) => i.codigo === this.valor));
                this.$nextTick(() => this.rolarParaAtivo());
                return;
            }
            this.carregando = true;
            this.erro = false;
            try {
                this.itens = await carregarCnaes(this.url);
            } catch {
                this.erro = true;
            } finally {
                this.carregando = false;
            }
        },

        fechar() {
            this.aberto = false;
            this.termo = '';
        },

        filtrar(texto) {
            this.termo = texto;
            this.limite = CNAE_LOTE;
            this.ativo = 0;
            if (!this.aberto) this.abrir();
            this.$refs.lista && (this.$refs.lista.scrollTop = 0);
        },

        escolher(item) {
            this.valor = item.codigo;
            this.rotulo = `${item.formatado} — ${item.descricao}`;
            this.fechar();
        },

        limpar() {
            this.valor = '';
            this.rotulo = '';
            this.termo = '';
            this.$refs.campo.focus();
        },

        maisAoRolar(evento) {
            const el = evento.target;
            if (el.scrollTop + el.clientHeight >= el.scrollHeight - 40 && this.limite < this.filtrados.length) {
                this.limite += CNAE_LOTE;
            }
        },

        mover(delta) {
            if (!this.aberto) { this.abrir(); return; }
            const total = this.filtrados.length;
            if (total === 0) return;
            this.ativo = Math.min(total - 1, Math.max(0, this.ativo + delta));
            if (this.ativo >= this.limite) this.limite += CNAE_LOTE;
            this.$nextTick(() => this.rolarParaAtivo());
        },

        confirmarAtivo() {
            const item = this.filtrados[this.ativo];
            if (this.aberto && item) this.escolher(item);
        },

        rolarParaAtivo() {
            this.$refs.lista?.querySelector(`[data-indice="${this.ativo}"]`)?.scrollIntoView({ block: 'nearest' });
        },

        get textoCampo() {
            return this.aberto ? this.termo : this.rotulo;
        },
    }));
});

/*
 * Livewire 4.4: o `updates` de uma requisição é montado com REFERÊNCIAS ao estado reativo quando o
 * diff é consolidado no pai (linha inteira de uma lista, `valores` inteiro). Quem digita depois do envio
 * muda esse objeto, a resposta é conciliada contra o texto novo em vez do que o servidor recebeu, e o
 * Livewire regrava o <input> com o valor antigo: teclas somem (STORY-095, D-1 e o CPF do sócio).
 * Copiar o payload no momento do envio mantém "o que foi enviado" imutável.
 */
document.addEventListener('livewire:init', () => {
    window.Livewire.interceptMessage(({ message }) => {
        const copia = JSON.parse(JSON.stringify(message.updates ?? {}));
        message.updates = copia;
        if (message.payload) {
            message.payload.updates = copia;
        }
    });
});
