# AURORA MULTIMARCAS

**E-commerce multimarcas desenvolvido em Laravel 13, PHP 8.3+, Blade, CSS responsivo/Tailwind, Alpine.js, Vite e MySQL.** Inclui vitrine, catálogo, variações por cor/tamanho, carrinho persistente, checkout, pagamentos e fretes demonstrativos, pedidos, área do cliente e administração.

A interface é inspirada nas cinco referências fornecidas: loja de moda editorial, página de produto com galeria, dashboard claro com indicadores e menu administrativo escuro. A imagem do banner é original; 45 ilustrações SVG de produtos foram criadas para a demonstração, sem dependência de imagens externas.

> **IMPORTANTE:** o arquivo ZIP contém **código-fonte e recursos gráficos**, não `vendor/`, `node_modules/` ou um banco MySQL já executando. Faça a instalação das dependências antes de abrir o site. **Não há credenciais comerciais configuradas; o checkout inicial é uma simulação.** A revisão de sintaxe foi feita, mas a instalação completa e os testes de navegador/MySQL dependem do ServBay local. Consulte `docs/STATUS_DE_VALIDACAO.md`.

## Correção de erro 500: `MissingAppKeyException` (11/11/2026)

Se `https://aurora.test` mostrar **"No application encryption key has been specified"**, o `.env` está sem uma chave válida ou há cache antigo. **Não apague o banco e não substitua o `.env` pelo `.env.example`.**

1. Faça backup da pasta do projeto, principalmente do `.env`, e exporte o MySQL.
2. Extraia o pacote de correção na **raiz do Laravel existente** (a pasta que contém `artisan`). Não apague `storage`, `vendor` ou `public/storage`.
3. Execute **`REPARAR_ERRO_500_WINDOWS.bat`** pelo terminal do ServBay que utiliza o mesmo PHP do site.
4. Abra novamente `https://aurora.test`. Se houver um erro diferente, consulte o log em `storage/logs/laravel.log`.

O reparador em `scripts/ensure_app_key.php` é independente do Composer: verifica se já há chave, procura recuperar a original em backups locais ou no cache de configurações, **não sobrescreve uma chave válida**, salva cópia do `.env` antes de alterar e somente cria nova chave em ambiente `local`/`testing` quando não encontra uma anterior. Nunca imprime o segredo. Se existem chaves de backup conflitantes ou o site está em produção, interrompe em vez de escolher arbitrariamente. Após a verificação o BAT limpa o cache via `php artisan optimize:clear` e valida a inicialização.

**Cuidado:** se uma instalação existente perdeu a chave original, gerar outra pode invalidar sessões, cookies e dados previamente criptografados. Sempre prefira restaurar a chave antiga a partir de um backup. Nem um ZIP do código-fonte nem este reparador conseguem recuperar uma chave que não foi salva em lugar algum.

O `ATUALIZAR_SERVBAY_WINDOWS.bat` também passou a verificar o `APP_KEY` **antes** de rodar migrations ou abrir rotas.

## Atualização corretiva — 11/11/2026 (site já instalado)

Se você já possui a Aurora rodando em **`https://aurora.test`** ou outro domínio do ServBay, **não reinstale nem zere o banco**. Faça backup do diretório e exporte seu MySQL, aplique o pacote de correção por cima do código atual e execute `ATUALIZAR_SERVBAY_WINDOWS.bat`. O atualizador **não sobrescreve `.env` ou `APP_KEY` e não executa seeders**. Ao aplicar o patch, ele cria apenas a coluna `active` nas variações, por migration aditiva. Para atualizar manualmente use `php artisan migrate --force`, `php artisan optimize:clear` e `npm run build` (se necessário, rode antes `npm install`).

O erro HTTP 500 no caminho `/categoria/moda-feminina` foi corrigido, junto à seleção de variações em detalhes de produto e outras regras de negócio. Veja o relatório em **`docs/CORRECOES_2026_11_11.md`** e o resumo de validação estática em **`docs/RESULTADO_AUDITORIA_ESTATICO.json`**. Esse relatório **não substitui** a execução dos testes e a validação no MySQL do seu ServBay.

**Atenção:** para uma atualização de instalação existente, **nunca execute** `php artisan migrate:fresh`, `db:wipe`, `key:generate --force` ou `migrate --seed`.

## 1. Pré-requisitos

- Windows com **ServBay** ativo, **Apache** (não é necessário Nginx), PHP **8.3 ou superior**, extensões ctype, curl, dom, fileinfo, filter, hash, mbstring, openssl, pcre, PDO, **pdo_mysql**, session, tokenizer, xml e zip.
- **MySQL 8+** em execução no ServBay.
- **Composer 2.2+** disponível no terminal configurado para o PHP do ServBay.
- Node.js 20+ e npm para recompilar Tailwind/Alpine/Vite. Há CSS e JavaScript de reserva em `public/assets/` para servir a interface mesmo antes da compilação.
- Apache com `mod_rewrite` habilitado e `AllowOverride All` para `.htaccess`.

## 2. Instalar no ServBay com Apache — Windows

1. Extraia **somente** a pasta `aurora-multimarcas` para o diretório que desejar utilizar com o ServBay. **Não** aponte o Apache para a raiz do projeto.
2. Abra **ServBay → Websites/Sites → Add Site** (o nome da opção pode variar na versão instalada):
   - Domínio local: `auroramultimarcas.test`.
   - Web server: **Apache**.
   - Tipo: PHP Website, PHP 8.3+.
   - **Document Root / raiz pública:** `CAMINHO_ONDE_EXTRAIU\aurora-multimarcas\public`.
   - Salve e confira se o ServBay aplicou as regras locais de host/Apache. Reinicie Apache se necessário.
3. Abra o MySQL do ServBay e crie **um banco vazio** com o nome escolhido, por exemplo `aurora_multimarcas`. Não é necessário importar arquivo `.sql`: o Laravel cria as tabelas pelas migrations.
4. Em **terminal com PHP, Composer e npm disponíveis**, entre na raiz do projeto. Exemplo: `cd C:\meu-diretorio\aurora-multimarcas` (ajuste seu caminho).
5. Copie `.env.example` para `.env` (no CMD: `copy .env.example .env`; no PowerShell: `Copy-Item .env.example .env`).
6. Edite o `.env`. **Localize no ServBay** os dados reais de conexão do MySQL: host, porta, usuário, senha e nome do banco. Não presuma credenciais padrão.

```dotenv
APP_NAME="Aurora Multimarcas"
APP_ENV=local
APP_DEBUG=true
APP_URL=http://auroramultimarcas.test
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=PORTA_EXIBIDA_NO_SERVBAY
DB_DATABASE=aurora_multimarcas
DB_USERNAME=USUARIO_EXIBIDO_NO_SERVBAY
DB_PASSWORD=SENHA_EXIBIDA_NO_SERVBAY
SHOP_PAYMENT_MODE=demo
SHOP_SHIPPING_MODE=demo
```

> As expressões em maiúsculas são **exemplos**: substitua pelos valores efetivos. O `.env` é secreto e não deve ser publicado.

7. Na raiz do projeto, execute os comandos **nesta ordem**:

```powershell
composer install
php artisan key:generate
npm install
npm run build
php artisan migrate --seed
php artisan storage:link
php artisan optimize:clear
```

8. Acesse **`http://auroramultimarcas.test`**. O painel fica em **`http://auroramultimarcas.test/admin`** (login requerido). Ative o Apache e o MySQL no ServBay.
9. Como alternativa, execute **`INSTALAR_SERVBAY_WINDOWS.bat`** após garantir PHP, Composer e npm no PATH. Se ainda não existir `.env`, ele será aberto para edição; o script usa `pause`, **não fecha automaticamente** ao ocorrer erro. A configuração do Website no ServBay ainda deve ser feita manualmente.

### Como identificar os dados do MySQL

Abra o serviço MySQL no ServBay e consulte a seção de configurações/conexão ou as informações do seu banco criado. Verifique a porta **real** do MySQL, o usuário e a senha que você definiu. Se houver dúvida sobre a conexão, execute `php artisan migrate:status` e confira mensagens e `storage/logs/laravel.log`. O `.env.example` não contém senhas reais.

### Se aparecer `Failed opening vendor/autoload.php`

A pasta `vendor` ainda não foi criada. Abra o terminal na **raiz** da pasta `aurora-multimarcas` e execute **`composer install`**. Verifique que existe `vendor/autoload.php`. Não copie um arquivo `autoload.php` isolado nem aponte o site diretamente para `index.php` fora da pasta `public`.

### Se o site mostrar uma tela 404 ou listar arquivos

Confirme que o Apache aponta para `...\aurora-multimarcas\public` e que `mod_rewrite` está ativo, `.htaccess` é respeitado e o domínio foi registrado no ServBay. Um site PHP Laravel não deve ser configurado como projeto estático.

### Se o banco acusar `Access denied` ou `Connection refused`

Verifique se o MySQL está iniciado, se a porta e credenciais correspondem ao serviço do ServBay e se o banco existe. Após atualizar `.env`, rode `php artisan optimize:clear`.

## 3. Logins de teste (apenas `APP_ENV=local`)

| Papel | E-mail | Senha |
|---|---|---|
| Superadministrador | `admin@auroramultimarcas.test` | `Admin@123456` |
| Administrador | `gerente@auroramultimarcas.test` | `Gerente@123456` |
| Operador | `operador@auroramultimarcas.test` | `Operador@123456` |
| Cliente | `cliente@auroramultimarcas.test` | `Cliente@123456` |

O seeder só cria essas contas em `local` ou `testing`. Em **produção**, `APP_ENV=production`, `APP_DEBUG=false`, faça migrações **sem demo seeders** e crie contas privadas, com senhas únicas.

## 4. Recursos do site

- Homepage com banner editável, categorias, destaques, novidades, promoções, marcas e newsletter com consentimento.
- Catálogo com busca por nome/SKU/marca, categoria, marca, faixa de preço, tamanho, cor, promoção, estoque, paginação e ordenação.
- Páginas com descrição, SKU, galeria, cor, tamanho, estoque por variação, zoom, cálculo **demonstrativo** de frete, avaliações moderadas, favoritos e produtos relacionados.
- Carrinho para visitante ou autenticado, mesclagem do carrinho após login, atualização, exclusão, cupom e total calculados no servidor.
- Checkout autenticado com endereço, escolha de frete, criação de pedido e reserva transacional de estoque. Pedido com histórico imutável dos itens da venda, mesmo que o produto seja alterado no catálogo.
- Página para simular pagamento **aprovado ou recusado**. Ao aprovar, a reserva vira baixa de estoque; ao cancelar/recusar, ela é liberada. O processamento é protegido contra repetição; pagamentos simulados **não movimentam dinheiro**.
- Área do cliente com login/cadastro, recuperação de senha por e-mail (driver `log` no ServBay local), perfil, endereços, histórico, favoritos, avaliações e pedidos de troca/devolução.

## 5. Painel administrativo

- Dashboard: receita **paga**, pedidos, pendentes, cancelados, clientes, produtos, ticket médio, histórico por dia e mais vendidos, com filtro de período.
- Produtos: listagem, filtros, edição, criação, arquivamento seguro, upload de fotos, categorias/marcas/coleções, SKU e variações com estoque próprio.
- Estoque: ajustes positivos/negativos, proteção contra estoque inferior ao reservado e histórico de movimentações.
- Pedidos: visão detalhada, status financeiro/logístico separados, rastreio, cancelamento de pendentes e reembolso **demonstrativo** mediante papel de gestão.
- Clientes: listagem, histórico agregado de pedidos e total confirmado.
- Relatórios: receita confirmada, descontos, fretes, produtos mais vendidos, filtros e exportação CSV.
- Outros: categorias, marcas, coleções, banners, cupons, avaliações, trocas/devoluções e identidade da loja.
- Papéis: `superadmin`, `admin`, `operator`, `customer`. O operador acessa produtos/pedidos/estoque, não configurações ou recursos financeiros especiais.

## 6. Pagamentos

Padrão: `SHOP_PAYMENT_MODE=demo`. **Não há gateway comercial ativo por padrão.** As opções simular aprovação/recusa ficam disponíveis na tela do pedido. Nenhum cartão é solicitado ou armazenado.

A pasta `app/Services/` contém os serviços `OrderService.php`, `PaymentService.php`, `ShippingService.php` e `MercadoPagoGateway.php`. Há uma integração **experimental, não homologada** com a API de preferências do Checkout Pro. Para testá-la:

1. Crie uma aplicação de teste no Mercado Pago e obtenha credenciais de teste **válidas**.
2. Em ambiente controlado, configure `SHOP_PAYMENT_MODE=mercadopago`, `MP_SANDBOX=true`, `MP_ACCESS_TOKEN` e `MP_WEBHOOK_SECRET` no `.env`. Nunca envie esses segredos para o Git ou para clientes.
3. Cadastre um URL HTTPS **publicamente acessível** como webhook: `https://SEU-DOMINIO-PUBLICO/webhooks/mercadopago`, atribua-o a `MP_WEBHOOK_URL` e inscreva eventos de pagamento. O `.test` local não pode ser chamado externamente pelo provedor sem túnel/ambiente apropriado.
4. Realize testes end-to-end com contas e cartões de teste do provedor; valide respostas, assinaturas de webhook, fluxos assíncronos, valor/identidade das transações, duplicidades, estornos e expiração antes de usar em produção.

A plataforma **não marca como pago** com base no retorno do navegador. O callback consulta o pagamento na API, verifica a assinatura e compara referência, moeda e valor antes de atualizar o pedido. O adapter não foi testado contra a API real neste ambiente.

Referências oficiais: [Checkout Pro – Preferências](https://www.mercadopago.com.br/developers/pt/reference/online-payments/checkout-pro-preferences/create-preference/post) e [Notificações/Webhooks](https://www.mercadopago.com.br/developers/pt/docs/checkout-pro-preferences/payment-notifications).

## 7. Entrega e frete

`SHOP_SHIPPING_MODE=demo` calcula modalidades simuladas: padrão R$ 18,90 (ou grátis a partir do valor editável em Configurações), expressa R$ 34,90 e retirada gratuita. **Esses valores não representam cotações reais**. Antes de produção, integre transportadora/Melhor Envio e valide CEP, peso, dimensões e regiões atendidas; não há API logística conectada.

## 8. Editar nome, marca, banners e catálogo

Acesse `/admin` como superadministrador/administrador → **Configurações**: nome, subtítulo, logo, favicon, e-mail, WhatsApp, cores, textos institucionais e título SEO. Em **Banners**, altere a imagem principal e secundária, textos e links. Em **Produtos**, troque as ilustrações SVG demonstrativas por fotos próprias e revise os SKUs, medidas e preços. As imagens enviadas vão para `storage/app/public` e são publicadas via `php artisan storage:link`.

Os textos jurídicos e fretes são modelos para desenvolvimento e precisam ser revisados antes de uso comercial. Substitua também domínios `.test` e e-mails fictícios.

## 9. Testes

```powershell
php artisan test
# ou
composer test
```

O arquivo `phpunit.xml` usa SQLite em memória para execução de testes automatizados isolados (requer `pdo_sqlite`). Para testes que simulem comportamento de concorrência real de transações e `SELECT ... FOR UPDATE`, execute uma suíte em um **MySQL de testes separado** e com requisições paralelas, nunca no banco de produção. Os testes em `tests/Feature` cobrem autenticação/roles, catálogo, variações, carrinho, criação de pedido, reserva de estoque, pagamento idempotente, isolamento de pedidos e ajustes de estoque. Não foram executados neste contêiner por ausência das dependências do Composer e de um driver de banco de dados configurado.

## 10. Estrutura principal

```text
aurora-multimarcas/
├── app/
│   ├── Http/{Controllers,Middleware,Requests}
│   ├── Models/
│   ├── Providers/
│   └── Services/
├── bootstrap/
├── config/
├── database/{migrations,seeders}
├── public/{assets,demo,index.php,.htaccess}
├── resources/{css,js,views}
│   └── views/{layouts,shop,admin,auth,partials}
├── routes/{web.php,console.php}
├── storage/
├── tests/Feature/
├── composer.json
├── package.json
├── .env.example
├── INSTALAR_SERVBAY_WINDOWS.bat
└── README.md
```

## 11. Antes de publicar em produção

Faça revisão integral de segurança, LGPD/CDC, fretes e políticas, HTTPS, CSP, monitoramento, expiração das reservas, e-mails transacionais, imagens comerciais licenciadas, SEO/sitemap, status de pagamento e tratamento de fraude, testes com MySQL real, acessibilidade e testes E2E. Um ambiente local demonstrativo **não equivale a uma loja homologada para receber dinheiro**.

A documentação detalhada de validação está em `docs/STATUS_DE_VALIDACAO.md`.
