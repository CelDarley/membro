# Diário de desenvolvimento (desev.md)

Este documento registra as ações implementadas, decisões de arquitetura, e os principais comandos utilizados desde a criação do backend e UI até o funcionamento em produção.

## Stack utilizada
- Backend: Flask (Python), Flask‑SQLAlchemy, Flask‑Migrate (Alembic), Flask‑JWT‑Extended
- Banco: MySQL 8 (driver PyMySQL; alternativa mysqlclient)
- E-mail: SMTP (Hostinger) para recuperação de senha
- UI: templates HTML/CSS/JS (vanilla) com ECharts
- Deploy: Gunicorn + Nginx + Certbot (HTTPS) em droplet Ubuntu (DigitalOcean)

## Estrutura do backend (membro-py)
- `app/__init__.py`: factory `create_app`, inicializa DB, Migrate, JWT, e registra blueprints
- `app/models.py`:
  - `User` (tabela `users_py`): `name`, `email` (único), `password_hash`, `role` (user/admin), `two_factor_enabled`, `phone`, `active`, `reset_code`, `reset_expires_at`
  - `Membro` (tabela `membros`) e relação N..N `membro_amigos`
  - `Lookup` (tabela `lookups`) com unique `(type, value)`
- `app/routes/`:
  - `auth.py`: `POST /api/auth/login`, `GET /api/auth/me`, `POST /api/auth/change-password`, `POST /api/auth/forgot-password`, `POST /api/auth/reset-password`
  - `users.py`: CRUD `/api/users` (listar/criar/atualizar/deletar), `POST /users/:id/toggle-active` e bootstrap do primeiro admin
  - `membros.py`: lista, detalhes, atualização de membros e buscas auxiliares
  - `lookups.py`: CRUD de cadastros auxiliares
  - `views.py`: entrega dos templates `login.html` e `membros.html`
- `migrations/`: versões Alembic (migrações de schema)
- `config.py`: `DATABASE_URL`, chaves secretas e SMTP

## UI (templates)
- `login.html`:
  - Form de login (POST `/api/auth/login`)
  - Fluxo "Esqueci a senha": modal para solicitar código e modal para redefinir (via `/api/auth/forgot-password` e `/api/auth/reset-password`)
- `membros.html`:
  - Abas: Tabela, Gráficos, Cadastros (admin) e Usuários (admin)
  - Tabela com cabeçalho e primeira/última coluna congelados; modal "Ver" com edição (admin)
  - Aba "Usuários" com CRUD, ativar/inativar, troca de senha por modal, e busca
  - Menu de perfil com modal de troca da própria senha
  - Ajustes de UX: toasts, espaçamento de botões na coluna Ações (gap 4px), z‑index da modal acima de tudo

## Funcionalidades incrementais implementadas
1. Base Flask + SQLAlchemy + Migrate + JWT (app factory, config e healthcheck `/api/health`)
2. Modelos (`User`, `Membro`, `Lookup`) e relacionamento `membro_amigos`
3. Autenticação: login, obtenção de perfil (`/me`), inclusão de claim `role` no JWT
4. CRUD de usuários:
   - Listar, criar (bootstrap: primeiro usuário vira admin), editar (inclui troca de senha), excluir, ativar/inativar
   - Flag `two_factor_enabled` persistida (gatilho/placeholder)
5. Cadastros (lookups) via API e UI
6. Tabela e gráficos (ECharts) na aba Gráficos
7. Recuperação de senha via e‑mail (Hostinger SMTP) e bloqueio de login para usuário inativo
8. Ajustes de UI/UX: z‑index das modais, coluna de ações com espaçamento, perfis e toasts

## Migrações do banco
- Campos `phone` e `active` em `users_py`
- Campos `reset_code` e `reset_expires_at` em `users_py`
- Ajuste do `env.py` do Alembic para limitar tabelas gerenciadas
- Em produção, ao detectar conflitos com migrações antigas (ex.: referências a `failed_jobs`), foi feito:
  - `flask --app manage.py db stamp base`
  - remoção das versões antigas em `migrations/versions/`
  - `flask --app manage.py db revision --autogenerate -m "create core tables"`
  - `flask --app manage.py db upgrade`

## Integração SMTP e segurança
- SMTP (Hostinger): envio de código de recuperação (6 dígitos, expira em 15 min)
- Bloqueio de login para `active = false`

## Comandos úteis (desenvolvimento)
```bash
# venv e dependências
python3 -m venv .venv
source .venv/bin/activate
pip install -U pip wheel
pip install Flask Flask-SQLAlchemy Flask-Migrate Flask-JWT-Extended PyMySQL python-dotenv gunicorn cryptography

# rodar local
flask --app manage.py run --host 0.0.0.0 --port 5000

# migrações
flask --app manage.py db migrate -m "msg"
flask --app manage.py db upgrade

# gerar chaves
python - << 'PY'
import secrets
print('SECRET_KEY=', secrets.token_urlsafe(64))
print('JWT_SECRET_KEY=', secrets.token_urlsafe(64))
PY
```

## Deploy (resumo)
```bash
# pacotes do servidor
apt update && apt upgrade -y
apt install -y git python3-venv python3-pip nginx ufw mysql-server certbot python3-certbot-nginx

# firewall
ufw allow OpenSSH
ufw allow 'Nginx Full'
ufw --force enable

# clonar como usuário membro
su - membro
mkdir -p ~/apps && cd ~/apps
git clone https://github.com/CelDarley/membro.git
cd membro/membro-py

# venv e deps
python3 -m venv .venv
source .venv/bin/activate
pip install -U pip wheel
pip install Flask Flask-SQLAlchemy Flask-Migrate Flask-JWT-Extended PyMySQL python-dotenv gunicorn cryptography

# .env
cat > .env << 'EOF'
SECRET_KEY=...
JWT_SECRET_KEY=...
DATABASE_URL=mysql+pymysql://membro:SENHA@127.0.0.1:3306/membro
MAIL_SERVER=smtp.hostinger.com
MAIL_PORT=587
MAIL_USE_TLS=true
MAIL_USERNAME=no-reply@catenasystem.com.br
MAIL_PASSWORD=...
MAIL_DEFAULT_SENDER=no-reply@catenasystem.com.br
EOF

# DB MySQL (como root)
mysql -u root <<'SQL'
CREATE DATABASE IF NOT EXISTS membro CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS 'membro'@'127.0.0.1' IDENTIFIED WITH caching_sha2_password BY 'SENHA';
GRANT ALL PRIVILEGES ON membro.* TO 'membro'@'127.0.0.1';
FLUSH PRIVILEGES;
SQL

# migrações
activate && flask --app manage.py db upgrade && deactivate

# systemd (gunicorn)
# /etc/systemd/system/membro.service -> ExecStart com gunicorn 127.0.0.1:8000
systemctl enable --now membro

# nginx e https
nginx -t && systemctl reload nginx
certbot --nginx -d membros.catenasystem.com.br --redirect -m seu-email@dominio.com --agree-tos -n
```

## Principais commits (resumo)
- `feat(users): endpoints /api/users e aba Usuários com CRUD (admin)`
- `fix(users): permitir bootstrap do primeiro usuário ...; melhorar mensagem de erro`
- `fix(ui): elevar z-index da modal Ver acima de toasts/header/filtro`
- `feat(users-ui): modal de Trocar senha na aba Usuários e integração PUT`
- `feat(auth): endpoint /api/auth/change-password e modal de troca de senha no perfil`
- `feat(auth): fluxo Esqueci a senha (código por log), modais na tela de login`
- `feat(auth): bloquear login de usuário inativo e enviar código por SMTP`

## Problemas encontrados e soluções (curto)
- HTML no login ("Unexpected token '<'"): ajustar Nginx para `proxy_pass` do `/` ao Gunicorn (sem servir HTML estático no `/api`) e usar HTTPS com Certbot
- MySQL 8 plugin: usar `caching_sha2_password` para o usuário do app; URL de conexão com `127.0.0.1`
- PyMySQL com MySQL 8: instalar `cryptography`; alternativa: `mysqlclient` e trocar driver para `mysql+mysqldb`
- Migrações herdadas com tabelas legadas: `db stamp base`, regenerar com `--autogenerate`, `db upgrade` 