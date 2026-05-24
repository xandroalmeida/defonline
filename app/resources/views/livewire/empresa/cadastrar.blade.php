<div>
    <x-page-header :title="'Cadastrar empresa'"
                   :subtitle="'Preencha os dados ou consulte na Receita Federal.'"/>

    <x-card>
        <form wire:submit="submit" novalidate class="flex flex-col gap-4">

            <fieldset class="rounded-md border border-[color:var(--color-border)] p-4 flex flex-col gap-2">
                <legend class="px-1 text-xs font-semibold uppercase tracking-wider text-[color:var(--color-secondary)]">
                    Tipo do documento
                </legend>
                @foreach ($tipos as $tipo)
                    <label class="flex gap-2.5 items-center cursor-pointer text-[color:var(--color-primary)]">
                        <input type="radio" name="tipo_documento" value="{{ $tipo->value }}"
                               wire:model.live="tipo_documento"
                               class="accent-[color:var(--color-tertiary)]"
                               dusk="empresa-tipo-{{ $tipo->value }}">
                        <span>{{ $tipo === \App\Domain\TipoDocumento::Cnpj ? 'CNPJ (empresa formalizada)' : 'CPF (autônomo)' }}</span>
                    </label>
                @endforeach
                @error('tipo_documento')
                    <p class="text-[color:var(--color-destructive)] text-sm m-0">{{ $message }}</p>
                @enderror
            </fieldset>

            <div>
                <x-label for="documento">
                    {{ $tipo_documento === 'cnpj' ? 'CNPJ' : 'CPF' }}
                </x-label>
                <x-input type="text" id="documento" wire:model.live.debounce.200ms="documento"
                         inputmode="numeric" autocomplete="off"
                         maxlength="{{ $tipo_documento === 'cnpj' ? 18 : 14 }}"
                         placeholder="{{ $tipo_documento === 'cnpj' ? '00.000.000/0000-00' : '000.000.000-00' }}"
                         x-data
                         x-on:input="
                             const tipo = $wire.tipo_documento;
                             const limite = tipo === 'cnpj' ? 14 : 11;
                             const d = $el.value.replace(/\D/g, '').slice(0, limite);
                             let m = d;
                             if (tipo === 'cnpj') {
                                 if (d.length > 12)      m = d.slice(0,2)+'.'+d.slice(2,5)+'.'+d.slice(5,8)+'/'+d.slice(8,12)+'-'+d.slice(12);
                                 else if (d.length > 8)  m = d.slice(0,2)+'.'+d.slice(2,5)+'.'+d.slice(5,8)+'/'+d.slice(8);
                                 else if (d.length > 5)  m = d.slice(0,2)+'.'+d.slice(2,5)+'.'+d.slice(5);
                                 else if (d.length > 2)  m = d.slice(0,2)+'.'+d.slice(2);
                             } else {
                                 if (d.length > 9)      m = d.slice(0,3)+'.'+d.slice(3,6)+'.'+d.slice(6,9)+'-'+d.slice(9);
                                 else if (d.length > 6) m = d.slice(0,3)+'.'+d.slice(3,6)+'.'+d.slice(6);
                                 else if (d.length > 3) m = d.slice(0,3)+'.'+d.slice(3);
                             }
                             $el.value = m;
                         "
                         dusk="empresa-documento"/>
                @error('documento')
                    <p class="text-[color:var(--color-destructive)] text-sm mt-1 mb-0" dusk="erro-documento">{{ $message }}</p>
                @enderror
            </div>

            @if ($tipo_documento === 'cnpj')
                @php
                    $digitosCnpj = preg_replace('/\D+/', '', $documento) ?? '';
                    $podeConsultar = strlen($digitosCnpj) === 14;
                @endphp
                <div class="flex flex-col gap-2">
                    <x-button type="button" variant="secondary" size="sm"
                              wire:click="consultarReceita"
                              wire:loading.attr="disabled" wire:target="consultarReceita"
                              :disabled="! $podeConsultar"
                              class="self-start"
                              dusk="empresa-consultar-receita">
                        <span wire:loading.remove wire:target="consultarReceita">Consultar Receita</span>
                        <span wire:loading wire:target="consultarReceita">Consultando…</span>
                    </x-button>
                    @if ($enriquecido)
                        <p class="text-sm rounded-md border border-[color:var(--color-border)] bg-[color:var(--color-neutral)] px-3 py-2 m-0 text-[color:var(--color-primary)]"
                           dusk="empresa-enriquecido-ok">
                            Dados pré-preenchidos pela Receita Federal — confira e ajuste se precisar.
                        </p>
                    @endif
                    @if ($mensagemFallback)
                        <p class="text-sm rounded-md border border-[color:var(--color-destructive)]/30 bg-[color:var(--color-destructive)]/5 text-[color:var(--color-destructive)] px-3 py-2 m-0"
                           role="alert" dusk="empresa-fallback">
                            {{ $mensagemFallback }}
                        </p>
                    @endif
                </div>
            @endif

            <div>
                <x-label for="razao_social">Razão social</x-label>
                <x-input type="text" id="razao_social" wire:model="razao_social"
                         autocomplete="organization" dusk="empresa-razao-social"/>
                @error('razao_social')
                    <p class="text-[color:var(--color-destructive)] text-sm mt-1 mb-0" dusk="erro-razao-social">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <x-label for="nome_fantasia">
                    Nome fantasia <span class="normal-case font-normal tracking-normal text-[color:var(--color-secondary)]">(opcional)</span>
                </x-label>
                <x-input type="text" id="nome_fantasia" wire:model="nome_fantasia" dusk="empresa-nome-fantasia"/>
                @error('nome_fantasia')
                    <p class="text-[color:var(--color-destructive)] text-sm mt-1 mb-0">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <x-label for="cnae">
                    CNAE <span class="normal-case font-normal tracking-normal text-[color:var(--color-secondary)]">(opcional — 7 dígitos)</span>
                </x-label>
                <x-input type="text" id="cnae" wire:model="cnae"
                         inputmode="numeric" maxlength="7" placeholder="0000000"
                         dusk="empresa-cnae"/>
                @error('cnae')
                    <p class="text-[color:var(--color-destructive)] text-sm mt-1 mb-0" dusk="erro-cnae">{{ $message }}</p>
                @enderror
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-[1fr_auto] gap-4">
                <div>
                    <x-label for="municipio">Município</x-label>
                    <x-input type="text" id="municipio" wire:model="municipio" dusk="empresa-municipio"/>
                    @error('municipio')
                        <p class="text-[color:var(--color-destructive)] text-sm mt-1 mb-0" dusk="erro-municipio">{{ $message }}</p>
                    @enderror
                </div>
                <div class="sm:w-32">
                    <x-label for="uf">UF</x-label>
                    <select id="uf" wire:model="uf" class="input" dusk="empresa-uf">
                        <option value="">—</option>
                        @foreach ($ufs as $ufCase)
                            <option value="{{ $ufCase->value }}">{{ $ufCase->value }}</option>
                        @endforeach
                    </select>
                    @error('uf')
                        <p class="text-[color:var(--color-destructive)] text-sm mt-1 mb-0" dusk="erro-uf">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <div>
                <x-label for="situacao_cadastral">Situação cadastral</x-label>
                <select id="situacao_cadastral" wire:model="situacao_cadastral" class="input"
                        dusk="empresa-situacao">
                    @foreach ($situacoes as $situacao)
                        <option value="{{ $situacao->value }}">{{ $situacao->rotulo() }}</option>
                    @endforeach
                </select>
                @error('situacao_cadastral')
                    <p class="text-[color:var(--color-destructive)] text-sm mt-1 mb-0">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <x-label for="data_fundacao">
                    Data de fundação <span class="normal-case font-normal tracking-normal text-[color:var(--color-secondary)]">(opcional)</span>
                </x-label>
                <x-input type="date" id="data_fundacao" wire:model="data_fundacao" dusk="empresa-data-fundacao"/>
                @error('data_fundacao')
                    <p class="text-[color:var(--color-destructive)] text-sm mt-1 mb-0" dusk="erro-data-fundacao">{{ $message }}</p>
                @enderror
            </div>

            {{--
                CA-18: form-actions seguem política Cancelar/Voltar.
                Desktop: [Cancelar] [Cadastrar empresa] na mesma linha à direita.
                Mobile: empilhado com primary no topo, Cancelar embaixo (column-reverse).
            --}}
            <div class="flex flex-col-reverse sm:flex-row sm:justify-end gap-3 pt-2">
                <x-button :href="route('home')" variant="secondary"
                          type="button"
                          class="sm:w-auto"
                          dusk="empresa-cancelar"
                          wire:navigate>
                    Cancelar
                </x-button>
                <x-button type="submit" variant="primary"
                          class="sm:w-auto"
                          dusk="empresa-submit">
                    Cadastrar empresa
                </x-button>
            </div>
        </form>
    </x-card>
</div>
