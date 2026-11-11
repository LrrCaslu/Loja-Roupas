# Aurora Multimarcas — revisão técnica e correções (11/11/2026)

## Problemas corrigidos

1. **HTTP 500 `/categoria/moda-feminina`**: o template comparava `request('category')` (modelo `Category` recebido pela rota) com `int`. O catálogo usa `request()->query('category')`, separando rota e query string.
2. **Filtros de categoria**: a categoria da URL não é combinada indevidamente com outro filtro de categoria. Ao navegar por uma categoria, o filtro exibe a seleção atual com link para o catálogo completo.
3. **Variações do produto**: o loop de produtos relacionados sobrescrevia a variável `$product` antes do JSON de seleção de cor/tamanho. O loop passa agora uma variável diferente ao componente do card.
4. **Variações retiradas da edição**: uma variação com pedido histórico não fica mais vendável após ser removida do formulário. A migration `2026_11_11_000003_add_active_to_product_variants` adiciona `active`, preservando estoque físico e histórico de venda. Variações arquivadas deixam de ser vendáveis; a administração pode consultá-las no estoque.
5. **CRUD de produtos**: melhoria na preservação de SKU quando as variações são reordenadas, na detecção de combinação duplicada e na validação da quantidade de imagens.
6. **Carrinho/checkout**: um item indisponível não é mais tratado como disponível ao calcular a cotação ou iniciar o checkout.
7. **Segurança**: sessões de contas inativas são bloqueadas; mudança de senha exige senha atual. Atualização das validações de pedidos e bloqueio do avanço logístico sem pagamento. Links de banners não permitem URL de protocolo relativo ou `javascript:`.
8. **Avaliações**: nova avaliação do mesmo cliente sobre o mesmo produto atualiza a pendente, em vez de gerar duplicação no filtro de avaliações públicas.
9. **Relatórios**: filtros de data são validados, produtos mais vendidos respeitam o período e exportação CSV pode considerar `from`/`to`.
10. **Instalação**: script inicial preserva a chave `APP_KEY` existente; novo script `ATUALIZAR_SERVBAY_WINDOWS.bat` aplica só migrations pendentes, limpa cache e recompila o frontend. Não executa seeders, não sobrescreve `.env` e não apaga o banco de dados.
11. **Pagamento**: o checkout indica corretamente se está configurado em modo demo ou Mercado Pago. A integração externa continua dependendo de validação/homologação.

## Aplicando ao seu ServBay/Apache (domínio aurora.test)

1. Faça backup do diretório completo do site **e exporte seu banco MySQL** antes de aplicar a atualização.
2. Use o ZIP `AURORA_MULTIMARCAS_PATCH_2026-11-11.zip` e extraia **os arquivos de dentro** sobre a **raiz atual do projeto Laravel** (pasta que contém `artisan`, `app`, `resources`, `routes`). Escolha mesclar/substituir arquivos de código, **sem apagar o conteúdo existente**. Não copie uma pasta `aurora-multimarcas` dentro da raiz Laravel existente.
3. Preserve `.env`, `APP_KEY`, `storage/`, `public/storage/` e `vendor/`. O patch não contém esses arquivos nem os altera.
4. Ative MySQL e Apache do ServBay. Abra um terminal na pasta do Laravel e rode **`ATUALIZAR_SERVBAY_WINDOWS.bat`**. O script não executa seeders nem recria tabelas. Alternativamente:

```powershell
php artisan migrate --force
php artisan optimize:clear
npm install
npm run build
php artisan route:list
```

5. Abra `https://aurora.test/categoria/moda-feminina`, selecione filtros, acesse produtos e valide variações; depois confira `/admin/produtos`, `/admin/estoque` e pedidos.
6. Se quiser rodar os testes e o PHP do seu ServBay tiver `pdo_sqlite`, use `php artisan test`. Os testes da aplicação não foram executados neste ambiente de construção porque aqui não havia `vendor`, Composer, PDO SQLite ou MySQL. Executar `php artisan view:cache` também é um teste útil para detectar falhas de compilação dos templates Blade.

**Atenção:** executar `php artisan migrate:fresh`, `php artisan db:wipe`, `php artisan key:generate --force` ou `php artisan migrate --seed` na loja existente pode causar perda de dados, redefinir credenciais de teste ou invalidar sessões. **Não utilize esses comandos para atualização.**

## Limitações restantes de verificação

O código foi inspecionado e validado estaticamente, inclusive a sintaxe de PHP e JavaScript. Não foi possível conectar ao MySQL do usuário, abrir o seu domínio privado no ServBay, executar o Laravel/Blade com Composer, confirmar fluxos no navegador ou homologar Mercado Pago. Há testes Feature adicionais no pacote, mas eles precisam ser executados no ServBay para verificar todas as rotas e transações com a instalação real. O sistema não deve ser considerado homologado para produção sem essa execução.
