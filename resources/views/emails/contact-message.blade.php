<x-mail::message>
# Nova mensagem de contato

**Nome:** {{ $name }}

**E-mail:** {{ $email }}

@if($contactSubject)
**Assunto:** {{ $contactSubject }}
@endif

<x-mail::panel>
{{ $messageBody }}
</x-mail::panel>

@if($user)
## Usuário logado

- **ID:** {{ $user->id }}
- **Nome:** {{ $user->name }}
- **E-mail:** {{ $user->email }}

@if($couple)
- **Casal:** {{ $couple->name }} (ID {{ $couple->id }})
@else
- **Casal:** sem casal vinculado
@endif
@else
Mensagem enviada por visitante não autenticado.
@endif

Atenciosamente,<br>
{{ config('app.name') }}
</x-mail::message>
