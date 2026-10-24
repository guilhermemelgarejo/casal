# Project rules

- Never create, execute or use migrations in this project, including deploy hooks and tests.
- Never run migrate, migrate:fresh, migrate:rollback, db:wipe or equivalent schema-changing commands.
- Do not automatically alter the existing database structure. Prepare explicit SQL for review if a structural change is unavoidable.
- Tests must use only SQLite :memory: and the independent fixture in tests/Support/schema.sqlite.sql, without invoking migrations.
- Preserve existing user accounts, passwords and financial history. Do not modify or delete the protected accounts listed in .cursor/rules/usuarios-existentes.mdc as part of maintenance or setup.
- Do not deploy or push without a user request.
