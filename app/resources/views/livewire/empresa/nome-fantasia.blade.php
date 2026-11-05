<div>
    <x-empresa.nome-fantasia-no-lugar :editando="$editando"
                                      modelo="nomeFantasia"
                                      :nome="$nomeAtual"
                                      :nome-da-receita="$nomeDaReceita"
                                      :texto-vazio="$textoVazio"
                                      :rotulo="$pessoaFisica ? 'nome comercial' : 'nome fantasia'"
                                      acao-editar="editar"
                                      acao-salvar="salvar"
                                      acao-cancelar="cancelar"
                                      prefixo-dusk="empresa-show"
                                      :salvo="$salvo"/>
</div>
