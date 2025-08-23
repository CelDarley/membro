# Guia de Deploy (deply.md)

Este guia cobre o deploy do projeto em um droplet Ubuntu (DigitalOcean) com Gunicorn + Nginx + HTTPS (Certbot), além de troubleshooting dos erros encontrados e respectivas soluções.

## 1) Requisitos
- Ubuntu 22.04+ (droplet)
- Domínio apontando via DNS A (ex.: `membros.catenasystem.com.br` -> IP do droplet)
- MySQL 8 instalado localmente
- SMTP (Hostinger ou outro) para recuperação de senha

## 2) Instalação de pacotes do sistema
```bash
ssh root@SEU_IP
apt update && apt upgrade -y
apt install -y git python3-venv python3-pip nginx ufw mysql-server certbot python3-certbot-nginx
ufw allow OpenSSH
ufw allow 'Nginx Full'
ufw --force enable
```

## 3) Banco de dados MySQL
Como root no MySQL:
```bash
mysql -u root
```
SQL:
```sql
CREATE DATABASE IF NOT EXISTS membro CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS 'membro'@'127.0.0.1' IDENTIFIED WITH caching_sha2_password BY 'SENHA_FORTE';
GRANT ALL PRIVILEGES ON membro.* TO 'membro'@'127.0.0.1';
FLUSH PRIVILEGES;
```
Teste:
```bash
mysql -u membro -p -h 127.0.0.1 -e "SELECT 1;" membro
```

## 4) Obter o código e preparar o Python
Criar usuário de aplicação e clonar o repositório:
```bash
adduser --disabled-password --gecos "" membro
usermod -aG sudo membro
su - membro
mkdir -p ~/apps && cd ~/apps
git clone https://github.com/CelDarley/membro.git
cd membro/membro-py
```
Venv e dependências:
```bash
python3 -m venv .venv
source .venv/bin/activate
pip install -U pip wheel
pip install Flask Flask-SQLAlchemy Flask-Migrate Flask-JWT-Extended PyMySQL python-dotenv gunicorn cryptography
```

## 5) Configuração do aplicativo (.env)
Crie o arquivo `.env`:
```bash
cat > .env << 'EOF'
SECRET_KEY=GERAR
JWT_SECRET_KEY=GERAR
DATABASE_URL=mysql+pymysql://membro:SENHA_FORTE@127.0.0.1:3306/membro
MAIL_SERVER=smtp.hostinger.com
MAIL_PORT=587
MAIL_USE_TLS=true
MAIL_USERNAME=no-reply@catenasystem.com.br
MAIL_PASSWORD=SENHA_EMAIL
MAIL_DEFAULT_SENDER=no-reply@catenasystem.com.br
EOF
```
Gerar chaves:
```bash
python - << 'PY'
import secrets
print('SECRET_KEY=', secrets.token_urlsafe(64))
print('JWT_SECRET_KEY=', secrets.token_urlsafe(64))
PY
```

## 6) Migrações do banco
```bash
source .venv/bin/activate
flask --app manage.py db upgrade
deactivate
```
Se houver conflito de migrações antigas, faça um reset seguro:
```bash
source .venv/bin/activate
flask --app manage.py db stamp base
rm -f migrations/versions/*.py
flask --app manage.py db revision --autogenerate -m "create core tables"
flask --app manage.py db upgrade
deactivate
```

## 7) Serviço Gunicorn (systemd)
Arquivo `/etc/systemd/system/membro.service`:
```ini
[Unit]
Description=Gunicorn Membro
After=network.target

[Service]
User=membro
Group=membro
WorkingDirectory=/home/membro/apps/membro/membro-py
Environment="PATH=/home/membro/apps/membro/membro-py/.venv/bin"
ExecStart=/home/membro/apps/membro/membro-py/.venv/bin/gunicorn -w 3 -b 127.0.0.1:8000 manage:app
Restart=always

[Install]
WantedBy=multi-user.target
```
Ativar e verificar:
```bash
systemctl daemon-reload
systemctl enable --now membro
systemctl status membro | cat
```

## 8) Nginx (proxy reverso)
Arquivo `/etc/nginx/sites-available/membros.catenasystem.com.br`:
```nginx
server {
    listen 80;
    server_name membros.catenasystem.com.br;

    location / {
        proxy_pass http://127.0.0.1:8000;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
        proxy_read_timeout 300;
    }

    client_max_body_size 20m;
}
```
Ativar site e recarregar:
```bash
ln -sf /etc/nginx/sites-available/membros.catenasystem.com.br /etc/nginx/sites-enabled/membros.catenasystem.com.br
rm -f /etc/nginx/sites-enabled/default
nginx -t && systemctl reload nginx
```

## 9) HTTPS (Certbot)
```bash
certbot --nginx -d membros.catenasystem.com.br --redirect -m seu-email@dominio.com --agree-tos -n
```
Teste:
```bash
curl -i https://membros.catenasystem.com.br/api/health
```

## 10) Criar usuário admin
```bash
su - membro
cd ~/apps/membro/membro-py
source .venv/bin/activate
python - << 'PY'
from app import create_app
from app.db import db
from app.models import User
app = create_app()
with app.app_context():
    u = User.query.filter_by(email='admin@trustme.com').first()
    if not u:
        u = User(name='Admin', email='admin@trustme.com', role='admin', active=True)
    u.set_password('admin123')
    db.session.add(u)
    db.session.commit()
    print('OK')
PY
deactivate
systemctl restart membro
```

## 11) Atualizações futuras
```bash
su - membro
cd ~/apps/membro
git pull
cd membro-py
source .venv/bin/activate
flask --app manage.py db upgrade
deactivate
sudo systemctl restart membro
```

---

## Troubleshooting (erros e soluções)

- Erro: HTML no login (Unexpected token '<')
  - Causa: Nginx servindo HTML em vez de proxy para o backend.
  - Solução: ajustar o server block para `proxy_pass http://127.0.0.1:8000;` no `location /` e remover `default`.

- Erro: 404 pelo domínio
  - Causa: Site não habilitado ou `default` ativo.
  - Solução: link simbólico de `sites-available` para `sites-enabled`, remover `default`, `nginx -t && systemctl reload nginx`.

- Erro MySQL 8: `Plugin 'mysql_native_password' is not loaded`
  - Causa: Auth padrão mudou no MySQL 8.
  - Solução: criar/alterar usuário com `IDENTIFIED WITH caching_sha2_password`.

- Erro PyMySQL: `cryptography package is required for sha256_password or caching_sha2_password`
  - Causa: driver precisa de `cryptography` com MySQL 8.
  - Solução: `pip install -U cryptography PyMySQL`. Alternativa: usar `mysqlclient` e `mysql+mysqldb` no `DATABASE_URL`.

- Erro 1146 `Table ... doesn't exist` ao migrar
  - Causa: migrações antigas referenciando tabelas legadas (ex.: `failed_jobs`).
  - Solução: `db stamp base`, remover `migrations/versions/*.py`, `db revision --autogenerate`, `db upgrade`.

- Erro `Access denied for user 'membro'@'localhost'`
  - Causa: usuário/host incorreto ou senha divergente do `.env`.
  - Solução: criar `membro@127.0.0.1`, garantir `DATABASE_URL` com `127.0.0.1`, sincronizar senhas.

- Erro de conexão local (curl porta 8000)
  - Verificar serviço: `systemctl status membro`.
  - Ver porta: `ss -ltnp | grep 8000`.
  - Teste direto no backend: `curl -i http://127.0.0.1:8000/api/health`.

- Dica: problemas com caracteres especiais na senha do banco
  - Use URL-encode ou troque a senha para algo sem parênteses/esp. (ou envolva a URL entre aspas no `.env`).

---

## Conclusão
Após seguir os passos acima, a aplicação estará em produção, servida pelo Nginx com HTTPS, backend em Gunicorn, base MySQL 8 e SMTP configurado para recuperação de senha. Para manutenção, utilize `git pull`, migrações e `systemctl restart membro`. 