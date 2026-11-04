<div>
    <x-page-header :title="$tipo_documento === 'cnpj' ? 'Cadastrar empresa' : 'Cadastrar autônomo'"
                   :subtitle="$tipo_documento === 'cnpj'
                       ? 'Informe o CNPJ ou o CPF. Com CNPJ, os dados vêm direto da Receita Federal.'
                       : 'Autônomo sem CNPJ: preencha os dados da sua atividade.'"/>

    @if ($tipo_documento === 'cnpj')
        {{-- STORY-063 / PDR-010: CNPJ primeiro; ficha RFB só-leitura; falha bloqueia. --}}
        <x-card>
            <form wire:submit="consultarReceita" novalidate class="flex flex-col gap-3">
                <div>
                    <x-label for="documento">CNPJ ou CPF</x-label>
                    <div class="flex flex-col sm:flex-row gap-3">
                        <x-input type="text" id="documento" wire:model.live.debounce.200ms="documento"
                                 inputmode="numeric" autocomplete="off"
                                 maxlength="18"
                                 placeholder="CNPJ ou CPF"
                                 x-data
                                 x-on:input="
                                     {{-- Máscara pelo nº de dígitos: até 11 = CPF, acima = CNPJ. --}}
                                     const d = $el.value.replace(/\D/g, '').slice(0, 14);
                                     let m = d;
                                     if (d.length > 11) {
                                         m = d.slice(0,2)+'.'+d.slice(2,5)+'.'+d.slice(5,8)+'/'+d.slice(8,12)+'-'+d.slice(12);
                                     } else if (d.length > 9) m = d.slice(0,3)+'.'+d.slice(3,6)+'.'+d.slice(6,9)+'-'+d.slice(9);
                                     else if (d.length > 6) m = d.slice(0,3)+'.'+d.slice(3,6)+'.'+d.slice(6);
                                     else if (d.length > 3) m = d.slice(0,3)+'.'+d.slice(3);
                                     if ($el.value !== m) { $el.value = m; $el.dispatchEvent(new Event('input')); }
                                 "
                                 dusk="empresa-documento"/>
                        <x-button type="submit" variant="primary"
                                  wire:loading.attr="disabled" wire:target="consultarReceita"
                                  class="sm:w-auto shrink-0"
                                  dusk="empresa-consultar-receita">
                            <span wire:loading.remove wire:target="consultarReceita">Consultar</span>
                            <span wire:loading wire:target="consultarReceita">Consultando…</span>
                        </x-button>
                    </div>
                </div>

                @if ($mensagemErro)
                    <p class="text-sm rounded-md border border-[color:var(--color-destructive)] bg-[color:var(--color-neutral)] text-[color:var(--color-destructive)] px-3 py-2 m-0"
                       role="alert" dusk="empresa-erro-consulta">
                        {{ $mensagemErro }}
                        @if ($empresaExistenteId)
                            <a href="{{ route('empresas.show', $empresaExistenteId) }}" class="underline font-semibold"
                               dusk="empresa-link-existente">Ver empresa</a>
                        @endif
                    </p>
                @endif

                @unless ($resultado)
                    <p class="m-0 text-sm text-[color:var(--color-secondary)]" dusk="empresa-dica-cpf">
                        Autônomo sem CNPJ? Informe seu CPF — em seguida você preenche os dados da sua atividade.
                    </p>
                @endunless
            </form>
        </x-card>

        @if ($resultado)
            <x-card class="mt-6" dusk="empresa-ficha-card">
                <p class="text-sm rounded-md border border-[color:var(--color-border)] bg-[color:var(--color-neutral)] px-3 py-2 m-0 mb-6 text-[color:var(--color-primary)]"
                   dusk="empresa-enriquecido-ok">
                    Dados oficiais da Receita Federal. Eles não podem ser alterados — use as observações para suas anotações.
                </p>

                <x-empresa.ficha-rfb :cnpj="$cnpjConsultado"
                                     :razao-social="$resultado->razaoSocial"
                                     :nome-fantasia="$resultado->nomeFantasia"
                                     :situacao="$resultado->situacaoCadastral"
                                     :data-abertura="$resultado->dataFundacao?->format('Y-m-d')"
                                     :ficha="$resultado->ficha"/>

                <form wire:submit="confirmar" novalidate class="flex flex-col gap-4 mt-6 pt-6 border-t border-[color:var(--color-border)]">
                    <div>
                        <x-label for="observacoes">
                            Observações <span class="normal-case font-normal tracking-normal text-[color:var(--color-secondary)]">(opcional — suas anotações sobre a empresa)</span>
                        </x-label>
                        <textarea id="observacoes" wire:model="observacoes" rows="4"
                                  maxlength="{{ \App\Livewire\Empresa\Cadastrar::OBSERVACOES_MAX }}"
                                  class="input min-h-24" dusk="empresa-observacoes">{{ $observacoes }}</textarea>
                        @error('observacoes')
                            <p class="text-[color:var(--color-destructive)] text-sm mt-1 mb-0" dusk="erro-observacoes">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="flex flex-col-reverse sm:flex-row sm:justify-end gap-3 pt-2">
                        <x-button :href="route('home')" variant="secondary"
                                  type="button"
                                  class="sm:w-auto"
                                  dusk="empresa-cancelar"
                                  wire:navigate>
                            Cancelar
                        </x-button>
                        <x-button type="submit" variant="primary" class="sm:w-auto" dusk="empresa-confirmar">
                            Confirmar e cadastrar
                        </x-button>
                    </div>
                </form>
            </x-card>
        @else
            <div class="mt-6 flex flex-col-reverse sm:flex-row sm:justify-end gap-3">
                <x-button :href="route('home')" variant="secondary"
                          type="button"
                          class="sm:w-auto"
                          dusk="empresa-cancelar"
                          wire:navigate>
                    Cancelar
                </x-button>
            </div>
        @endif
    @else
        {{-- Autônomo (CPF): cadastro manual, campos editáveis (STORY-014; PDR-010 item 6). --}}
        <x-card>
            <form wire:submit="submit" novalidate class="flex flex-col gap-4">
                <p class="m-0 text-sm text-[color:var(--color-secondary)]">
                    <button type="button" wire:click="usarCnpj" class="underline font-semibold text-[color:var(--color-tertiary)]"
                            dusk="empresa-usar-cnpj">Tenho CNPJ — buscar na Receita</button>
                </p>

                <div>
                    <x-label for="documento">CPF</x-label>
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

            <div>
                <x-label for="razao_social">Nome completo</x-label>
                <x-input type="text" id="razao_social" wire:model="razao_social"
                         autocomplete="name" dusk="empresa-razao-social"/>
                @error('razao_social')
                    <p class="text-[color:var(--color-destructive)] text-sm mt-1 mb-0" dusk="erro-razao-social">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <x-label for="nome_fantasia">
                    Nome comercial <span class="normal-case font-normal tracking-normal text-[color:var(--color-secondary)]">(opcional — como seus clientes te conhecem)</span>
                </x-label>
                <x-input type="text" id="nome_fantasia" wire:model="nome_fantasia" dusk="empresa-nome-fantasia"/>
                @error('nome_fantasia')
                    <p class="text-[color:var(--color-destructive)] text-sm mt-1 mb-0">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <x-label for="cnae">
                    Atividade principal <span class="normal-case font-normal tracking-normal text-[color:var(--color-secondary)]">(opcional — busque pelo código CNAE ou pelo nome)</span>
                </x-label>
                <x-cnae-select id="cnae" wire:model="cnae" :valor-inicial="$cnae" dusk="empresa-cnae"/>
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
                    <x-select id="uf" wire:model="uf" dusk="empresa-uf">
                        <option value="">—</option>
                        @foreach ($ufs as $ufCase)
                            <option value="{{ $ufCase->value }}">{{ $ufCase->value }}</option>
                        @endforeach
                    </x-select>
                    @error('uf')
                        <p class="text-[color:var(--color-destructive)] text-sm mt-1 mb-0" dusk="erro-uf">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <div>
                <x-label for="data_fundacao">
                    Início da atividade <span class="normal-case font-normal tracking-normal text-[color:var(--color-secondary)]">(opcional)</span>
                </x-label>
                <x-input type="date" id="data_fundacao" wire:model="data_fundacao" dusk="empresa-data-fundacao"/>
                @error('data_fundacao')
                    <p class="text-[color:var(--color-destructive)] text-sm mt-1 mb-0" dusk="erro-data-fundacao">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <x-label for="observacoes">
                    Observações <span class="normal-case font-normal tracking-normal text-[color:var(--color-secondary)]">(opcional — suas anotações)</span>
                </x-label>
                <textarea id="observacoes" wire:model="observacoes" rows="4"
                          maxlength="{{ \App\Livewire\Empresa\Cadastrar::OBSERVACOES_MAX }}"
                          class="input min-h-24" dusk="empresa-observacoes">{{ $observacoes }}</textarea>
                @error('observacoes')
                    <p class="text-[color:var(--color-destructive)] text-sm mt-1 mb-0" dusk="erro-observacoes">{{ $message }}</p>
                @enderror
            </div>

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
                        Cadastrar
                    </x-button>
                </div>
            </form>
        </x-card>
    @endif
</div>
