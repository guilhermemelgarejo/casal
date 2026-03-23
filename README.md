# Finanças do Casal 💑

Um aplicativo web moderno e responsivo para controle financeiro compartilhado entre casais, desenvolvido com Laravel 11.

## 🚀 Funcionalidades

- **Dashboard Inteligente**: Visão geral de receitas, despesas e saldo do período.
- **Análise de Fluxo por Conta**: Saiba exatamente onde e como seu dinheiro foi gasto, com detalhamento cruzado entre conta/cartão e forma de pagamento.
- **Filtro por Período**: Navegação intuitiva por meses e anos através de um seletor de calendário.
- **Gestão de Lançamentos**: Registro detalhado de receitas e despesas com categoria, conta, forma de pagamento e data.
- **Gerenciamento de Categorias**: Organize seus gastos com categorias personalizadas e cores de identificação.
- **Contas e Cartões**: Cadastre seus bancos e cartões de crédito para um controle preciso de cada fonte de recurso.
- **Sistema Compartilhado**: Feito para casais, permitindo que dois usuários compartilhem o mesmo ambiente financeiro com logins separados.
- **Responsivo**: Interface otimizada para uso em computadores e dispositivos móveis.

## 🛠️ Tecnologias Utilizadas

- **Framework**: [Laravel 11](https://laravel.com)
- **Autenticação**: [Laravel Breeze](https://laravel.com/docs/11.x/starter-kits#laravel-breeze)
- **Frontend**: [Tailwind CSS](https://tailwindcss.com) e [Alpine.js](https://alpinejs.dev)
- **Banco de Dados**: MariaDB / MySQL
- **Linguagem**: PHP 8.2+

## ⚙️ Instalação e Configuração

1. **Clonar o repositório:**
   ```bash
   git clone https://github.com/guilhermemelgarejo/casal.git
   cd casal
   ```

2. **Instalar dependências do PHP:**
   ```bash
   composer install
   ```

3. **Instalar dependências do Frontend:**
   ```bash
   npm install
   npm run build
   ```

4. **Configurar o ambiente:**
   - Copie o arquivo `.env.example` para `.env`
   - Configure suas credenciais de banco de dados no arquivo `.env`
   - Gere a chave da aplicação:
     ```bash
     php artisan key:generate
     ```

5. **Executar as migrações:**
   ```bash
   php artisan migrate
   ```

6. **Iniciar o servidor local:**
   ```bash
   php artisan serve
   ```

## 📄 Licença

Este projeto é um software de código aberto licenciado sob a [MIT license](https://opensource.org/licenses/MIT).
