{{--
    Alerta de confirmação do app, no lugar do `confirm()` do navegador. Fica uma vez só
    no layout e abre pelo evento que `<x-botao-confirmar>` dispara. O `<dialog>` nativo
    cuida do foco, do Esc e de bloquear a tela atrás. O foco começa em Cancelar.
--}}
<dialog x-data="{
            titulo: '',
            mensagem: '',
            rotulo: '',
            acao: null,
            abrir(pedido) {
                Object.assign(this, pedido);
                this.$root.showModal();
            },
            confirmar() {
                this.$root.close();
                this.acao();
            },
        }"
        x-on:pedir-confirmacao.window="abrir($event.detail)"
        x-on:click.self="$root.close()"
        aria-labelledby="confirmacao-titulo"
        aria-describedby="confirmacao-mensagem"
        class="print:hidden m-auto w-[calc(100%-2rem)] max-w-[28rem] rounded-lg border border-[color:var(--color-border)] bg-[color:var(--color-surface)] p-0 text-[color:var(--color-primary)] shadow-md backdrop:bg-black/40"
        dusk="confirmacao">
    <div class="p-6">
        <h2 id="confirmacao-titulo" class="text-lg font-medium m-0 mb-2" x-text="titulo"></h2>
        <p id="confirmacao-mensagem" class="text-sm text-[color:var(--color-secondary)] m-0 mb-6" x-text="mensagem" dusk="confirmacao-mensagem"></p>
        <div class="flex justify-end gap-2">
            <x-button type="button" variant="secondary" autofocus x-on:click="$root.close()" dusk="confirmacao-cancelar">Cancelar</x-button>
            <x-button type="button" variant="destructive" x-on:click="confirmar()" dusk="confirmacao-confirmar"><span x-text="rotulo"></span></x-button>
        </div>
    </div>
</dialog>
