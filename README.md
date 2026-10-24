# DuoZen

Um aplicativo web para controle financeiro compartilhado entre casais, desenvolvido com Laravel 13 e PHP 8.4+.

O projeto não utiliza migrations. Não execute comandos de migration ou recriação de banco; o deploy também não altera a estrutura do banco. Os arquivos antigos em `database/migrations/` são históricos e não devem ser executados.

Configure um banco existente no `.env`, instale as dependências com `composer install` e execute os testes com `php vendor/phpunit/phpunit/phpunit`. A suíte usa uma fixture independente em SQLite em memória, sem migrations nem dados reais.

Administradores são configurados por `DUOZEN_ADMIN_USER_IDS` ou `DUOZEN_ADMIN_EMAILS`, sempre com e-mail verificado. O ID do casal não concede mais administração. Antes de publicar, selecione PHP 8.4+ na hospedagem e configure os administradores no `.env` de produção.

## 🚀 Funcionalidades

- **Dashboard Inteligente**: Visão geral de receitas, despesas e saldo do período.
- **Análise de Fluxo por Conta**: Saiba exatamente onde e como seu dinheiro foi gasto, com detalhamento cruzado entre conta/cartão e forma de pagamento.
- **Filtro por Período**: Navegação intuitiva por meses e anos através de um seletor de calendário.
- **Gestão de Lançamentos**: Registro detalhado de receitas e despesas com categoria, conta, forma de pagamento e data.
- **Gerenciamento de Categorias**: Organize seus gastos com categorias personalizadas e cores de identificação.
- **Contas e Cartões**: Cadastre seus bancos e cartões de crédito para um controle preciso de cada fonte de recurso.
- **Sistema Compartilhado**: Feito para casais, permitindo que dois usuários compartilhem o mesmo ambiente financeiro com logins separados.
- **Responsivo**: Interface otimizada para uso em computadores e dispositivos móveis.

##  Licença

Este projeto é um software de código aberto licenciado sob a [licença MIT](https://opensource.org/licenses/MIT).
