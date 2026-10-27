# Login e cadastro com Google

A integração usa Laravel Socialite no servidor, com sessão, `state` e PKCE. O login
tradicional continua disponível. A opção Google vem desativada por padrão.

## Preparação do banco

Revisar `docs/sql/2026-10-26_login_google.sql` e conferir a estrutura real de `users`
antes de qualquer aplicação manual. O SQL propõe `social_accounts` e senha nullable
para novos cadastros exclusivamente Google. Não há migrations, backfill nem
alteração dos hashes existentes. Nenhum hook aplica esse SQL.

Não ativar a integração antes da aplicação manual do SQL revisado. Os testes usam
somente SQLite `:memory:` e `tests/Support/schema.sqlite.sql`.

## Configuração OAuth

1. No Google Cloud / Google Auth Platform, configurar o aplicativo, público,
   marca, domínio e links públicos dos Termos e da Política de Privacidade.
2. Criar um cliente OAuth do tipo **Aplicativo da Web**, dedicado ao login DuoZen.
   As credenciais e permissões de backup do Google Drive são independentes.
3. Cadastrar a URI de retorno exata, incluindo protocolo, domínio, porta e caminho:
   `https://SEU-DOMINIO/auth/google/callback`. Em desenvolvimento, usar uma origem
   aceita pelo Google, como `http://localhost:8000/auth/google/callback`, e abrir
   todo o fluxo nessa mesma origem. Não iniciar em `casal.localhost` e retornar
   em `localhost`: o cookie de sessão/state não acompanha a troca de domínio.
4. Enquanto o aplicativo estiver em teste, configurar os usuários de teste no
   console. Conferir a configuração do público antes de liberar o acesso geral.
5. Preencher no `.env` do ambiente, mantendo o segredo fora do repositório:

   ```dotenv
   GOOGLE_LOGIN_ENABLED=true
   GOOGLE_LOGIN_CLIENT_ID=CLIENTE_OAUTH
   GOOGLE_LOGIN_CLIENT_SECRET=SEGREDO_OAUTH
   GOOGLE_LOGIN_REDIRECT_URI=https://SEU-DOMINIO/auth/google/callback
   ```

6. Se houver cache de configuração/rotas, atualizá-lo pelo procedimento do ambiente
   (`php artisan config:clear` e `php artisan route:clear`). Em produção, usar HTTPS,
   cookie seguro e `SESSION_SAME_SITE=lax`. O domínio do cookie deve corresponder
   à origem utilizada no início e no retorno do fluxo.

Não é preciso liberar Drive, Calendar, Gmail ou acesso offline. Os únicos escopos
são `openid`, `email` e `profile`. Access tokens e refresh tokens não são gravados
em banco, logs ou sessão. Na sessão permanece somente o identificador e os dados
necessários ao cadastro, por até dez minutos; o prazo é conferido a cada uso.

## Comportamento

- O identificador do vínculo é o `sub` retornado pelo Google, com unicidade de
  provedor/identidade e de usuário/provedor. Mudanças de e-mail no Google não
  sobrescrevem nome, e-mail, senha ou permissões do perfil local.
- Um novo usuário confirma nome e aceite dos termos antes de criar a conta. O
  aceite usa o canal `legal`, com a mesma versão dos termos do cadastro comum.
- O convite é mantido na sessão e revalidado pelo `CoupleMembershipService` na
  conclusão. Convite inválido ou casal lotado desfaz usuário e vínculo na transação.
- E-mail local já existente exige entrada na conta existente e vinculação no
  perfil com a senha atual. Se a pessoa perdeu a senha, pode usar a recuperação
  por e-mail. Não há associação automática nem atualização de usuários protegidos.
- `email_verified` do Google deve ser verdadeiro. Para novos usuários Gmail ou
  Workspace (`hd` presente), o e-mail local começa verificado. Para outros
  domínios, permanece não verificado e o Laravel envia seu link de verificação.
  O uso comum segue a política atual da aplicação; privilégios que dependem de
  e-mail verificado não são liberados por um e-mail externo não confirmado.
- Contas sem senha local confirmam novamente a identidade Google antes de definir
  senha, trocar o e-mail ou excluir a conta. A confirmação dura cinco minutos,
  vale apenas para o usuário e a ação solicitada, e é consumida ao concluir.
- Para contas com senha local, as regras existentes de senha atual continuam.
  Desvincular Google exige senha local e sua confirmação. Contas exclusivamente
  Google precisam primeiro definir uma senha para não perder o acesso.
- A exclusão do usuário revoga o vínculo inclusive ao preservar um perfil
  anonimizado com histórico financeiro. Perfis anonimizados não autenticam.
- Configurar `GOOGLE_LOGIN_ENABLED=false` desativa as rotas e oculta os botões.
  Quem tem somente Google pode definir senha pela recuperação por e-mail. Não
  remover tabelas ou identidades como procedimento de reversão.

## Rotas e validação

`routes/auth.php`: início e retorno em `GET /auth/google/redirect` e
`GET /auth/google/callback`; conclusão de cadastro em `GET/POST /auth/google/register`;
vinculação/desvinculação em `POST/DELETE /auth/google/link`; confirmação de identidade
em `POST /auth/google/confirm`. Rotas têm limite de tentativas; mutações usam CSRF.

Teste automatizado da integração, sem acessar o Google:

```powershell
php vendor/bin/phpunit tests/Feature/Auth/GoogleAuthenticationTest.php
```

Antes de liberar, validar com credenciais reais: novo cadastro com e sem convite,
conta existente vinculada, recusa de consentimento, confirmação de identidade,
senha local e recuperação. O teste automatizado simula somente a rede Google e
exercita o provider real, incluindo validação de state e PKCE.

Referências: [Laravel Socialite](https://laravel.com/docs/13.x/socialite),
[Google OpenID Connect](https://developers.google.com/identity/openid-connect/openid-connect).
