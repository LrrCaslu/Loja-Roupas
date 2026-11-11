# Arquitetura e decisões de engenharia

- **Laravel 13 MVC**: rotas em `routes/web.php`, controllers separados por domínio, entidades com Eloquent, validação de entrada na camada HTTP e regras críticas em `app/Services`.
- **MySQL**: `2026_01_01_000001_*` (contas/sessões) e `2026_01_01_000002_*` (comércio completo). `foreign keys`, índices e constraints evitam registros órfãos em operações normais.
- **Domínios**: Catalog (Category, Brand, Collection, Product, Variant, ProductImage); Cart (Cart, CartItem); Order (Order, OrderItem, Payment, Shipment); CRM (User, Address, Favorites, Review, ReturnRequest); Marketing (Coupon, Banner, NewsletterSubscriber, Setting); Inventory (StockMovement); Audit (AuditLog).
- **Snapshots de venda**: `order_items` retém nome, SKU, variação, quantidade e preço independentemente de futuras mudanças no produto.
- **Reserva transacional**: `OrderService::place()` bloqueia carrinho, itens e variações, verifica `stock - reserved`, cria pedido e reserva antes da confirmação. `PaymentService` bloqueia o pedido no processamento, evita repetição e converte a reserva em baixa de `stock` após aprovação. Em recusa/cancelamento pendente, libera reservas. Reembolsos demonstrativos podem repor estoque somente mediante opção explícita.
- **Papéis**: campo `role` no usuário + middleware `RequireRole`; operações administrativas sensíveis utilizam validação adicional de papel.
- **Client UI**: Blade e CSS autoral mobile-first, Tailwind v4/Alpine.js/Vite integrados para desenvolvimento. Recursos locais precompilados ficam em `public/assets` para fallback no Apache.
- **Imagem**: assets originais em `public/demo` e uploads validados no disco público do Laravel (`storage/app/public`).
- **Pagamento**: modo `demo` default. O adapter MercadoPagoGateway usa API de preferências, assinatura HMAC do webhook, consulta remota de pagamento e processamento idempotente, mas só pode ser homologado com conta e infraestrutura externas.
- **Auditoria**: `Audit::record()` registra usuário, ação, entidade, data e IP sem expor chaves ou dados de cartão.

## Pontos de extensão

- Provedor real de frete e cálculo de prazo por CEP/volume.
- Expiração automática de pedidos/reservas (job agendado e testes de concorrência MySQL).
- API REST de aplicativo móvel, se necessária, com tokens e autorização.
- E-mail transacional real por SMTP/serviço de e-mail.
- Otimização de imagem WebP/AVIF no momento do upload.
- Histórico detalhado de transições em tabela dedicada e observabilidade.
