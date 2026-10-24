# Project rules

- Never create, execute or use migrations in this project, including deploy hooks and tests.
- Never run migrate, migrate:fresh, migrate:rollback, db:wipe or equivalent schema-changing commands.
- Do not automatically alter the existing database structure. Prepare explicit SQL for review if a structural change is unavoidable.
- Tests must use only SQLite :memory: and the independent fixture in tests/Support/schema.sqlite.sql, without invoking migrations.
- Preserve existing user accounts, passwords and financial history. Do not modify or delete the protected accounts listed in .cursor/rules/usuarios-existentes.mdc as part of maintenance or setup.
- Do not deploy or push without a user request.

## Commits e branches

- Antes de cada `git commit`, apresentar a mensagem exata e aguardar confirmação específica do usuário. Pedidos genéricos para organizar ou criar commits não dispensam essa confirmação.
- Criar commits somente na `develop`; conferir a branch e trocar para ela antes de preparar commits. Nunca commitar diretamente na `master`.
- Se a `develop` não existir ou a troca conflitar com alterações locais, pedir orientação sem descartar mudanças ou reescrever o histórico.
- Fazer merge para `master` apenas mediante pedido explícito do usuário.
