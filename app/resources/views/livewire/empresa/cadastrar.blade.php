<div class="container container--wide">
    <h1>Cadastrar empresa</h1>
    <p class="info">
        Preencha os dados da sua empresa para começar. Você pode pular o passo da Receita Federal e digitar os dados manualmente.
    </p>

    <form wire:submit="submit" novalidate>
        <fieldset class="grupo">
            <legend>Tipo do documento</legend>
            @foreach ($tipos as $tipo)
                <label class="radio">
                    <input type="radio" name="tipo_documento" value="{{ $tipo->value }}"
                           wire:model.live="tipo_documento"
                           dusk="empresa-tipo-{{ $tipo->value }}">
                    <span>{{ $tipo === \App\Domain\TipoDocumento::Cnpj ? 'CNPJ (empresa formalizada)' : 'CPF (autônomo)' }}</span>
                </label>
            @endforeach
            @error('tipo_documento') <p class="erro">{{ $message }}</p> @enderror
        </fieldset>

        <label for="documento">
            {{ $tipo_documento === 'cnpj' ? 'CNPJ' : 'CPF' }}
        </label>
        <input type="text" id="documento" wire:model="documento"
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
               dusk="empresa-documento">
        @error('documento') <p class="erro" dusk="erro-documento">{{ $message }}</p> @enderror

        <label for="razao_social">Razão social</label>
        <input type="text" id="razao_social" wire:model="razao_social"
               autocomplete="organization" dusk="empresa-razao-social">
        @error('razao_social') <p class="erro" dusk="erro-razao-social">{{ $message }}</p> @enderror

        <label for="nome_fantasia">Nome fantasia <small>(opcional)</small></label>
        <input type="text" id="nome_fantasia" wire:model="nome_fantasia"
               dusk="empresa-nome-fantasia">
        @error('nome_fantasia') <p class="erro">{{ $message }}</p> @enderror

        <label for="cnae">CNAE <small>(opcional — 7 dígitos)</small></label>
        <input type="text" id="cnae" wire:model="cnae"
               inputmode="numeric" maxlength="7" placeholder="0000000"
               dusk="empresa-cnae">
        @error('cnae') <p class="erro" dusk="erro-cnae">{{ $message }}</p> @enderror

        <label for="municipio">Município</label>
        <input type="text" id="municipio" wire:model="municipio" dusk="empresa-municipio">
        @error('municipio') <p class="erro" dusk="erro-municipio">{{ $message }}</p> @enderror

        <label for="uf">UF</label>
        <select id="uf" wire:model="uf" dusk="empresa-uf">
            <option value="">— selecione —</option>
            @foreach ($ufs as $ufCase)
                <option value="{{ $ufCase->value }}">{{ $ufCase->value }}</option>
            @endforeach
        </select>
        @error('uf') <p class="erro" dusk="erro-uf">{{ $message }}</p> @enderror

        <label for="situacao_cadastral">Situação cadastral</label>
        <select id="situacao_cadastral" wire:model="situacao_cadastral" dusk="empresa-situacao">
            @foreach ($situacoes as $situacao)
                <option value="{{ $situacao->value }}">{{ $situacao->rotulo() }}</option>
            @endforeach
        </select>
        @error('situacao_cadastral') <p class="erro">{{ $message }}</p> @enderror

        <label for="data_fundacao">Data de fundação <small>(opcional)</small></label>
        <input type="date" id="data_fundacao" wire:model="data_fundacao" dusk="empresa-data-fundacao">
        @error('data_fundacao') <p class="erro" dusk="erro-data-fundacao">{{ $message }}</p> @enderror

        <button type="submit" class="primary" dusk="empresa-submit">Cadastrar empresa</button>
    </form>
</div>
