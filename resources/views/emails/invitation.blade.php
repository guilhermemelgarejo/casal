<x-mail::message>
# Olá!

**{{ $user->name }}** convidou você para gerenciar as finanças do casal **{{ $couple->name }}** no nosso aplicativo.

Com o aplicativo, vocês poderão:
- Controlar gastos e receitas de forma compartilhada.
- Definir orçamentos mensais.
- Acompanhar o fluxo financeiro por conta e forma de pagamento.

Para aceitar o convite, use o código abaixo no sistema:

<x-mail::panel>
**{{ $couple->invite_code }}**
</x-mail::panel>

<x-mail::button :url="route('register', ['invite_code' => $couple->invite_code])">
Criar minha conta e entrar no casal
</x-mail::button>

Se você já possui uma conta, basta fazer o login e inserir o código na seção de Gerenciar Casal.

Atenciosamente,<br>
{{ config('app.name') }}
</x-mail::message>
