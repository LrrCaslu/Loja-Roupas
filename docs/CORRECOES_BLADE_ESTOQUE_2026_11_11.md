# Correções de produto e estoque — 11/11/2026

## 1. Produto — ParseError na view

**Sintoma:** `/produto/camiseta-algodao-premium-12`, `resources/views/shop/product.blade.php:4`, `Unclosed '[' does not match ')'`.

**Causa:** expressão complexa de `@json` no Blade continha uma `fn` PHP retornando um array; a análise dos argumentos da diretiva gerou PHP inválido.

**Correção:** `ShopController::product` agora constrói `$variantsForJs` com `id`, `size`, `color`, `available`. A view consome apenas `@json($variantsForJs)`.

## 2. Estoque — relacionamento Eloquent ausente

**Sintoma:** `/admin/estoque`, `RelationNotFoundException` para `StockMovement::with('variant.product')`.

**Causa:** a classe `App\Models\StockMovement` não declarava o relacionamento `variant`.

**Correção:** `belongsTo(Variant::class, 'variant_id')` e `belongsTo(User::class, 'user_id')` adicionados ao model.

## 3. Testes e verificações

- Testes HTTP de regressão adicionados em `tests/Feature/ProjectRegressionTest.php` para página de produto e estoque.
- `php -l` dos arquivos PHP do projeto: verificado.
- 508 expressões `{{ ... }}` dos templates Blade verificadas por sintaxe PHP.
- `VERIFICAR_CORRECOES_WINDOWS.bat` usa o Laravel instalado para `view:cache` e passa `php -l` nas views compiladas. **Executar no ServBay do usuário.**

**Limite:** os testes HTTP do Laravel e a compilação real de Blade não foram executados neste ambiente, porque ele não tem a pasta `vendor` nem o banco local do usuário. A resolução das duas falhas deve ser confirmada nas URLs de origem.

O patch não contém migrations e não demanda alterações no MySQL, `.env` ou `APP_KEY`.
