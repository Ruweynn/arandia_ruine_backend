# LavaLust Product API

This repository contains the independent PHP API backend built with LavaLust.

## Requirements

- PHP 8.2 or newer
- Composer
- MySQL database
- A Render account for production deployment

## Local setup

1. Copy `.env.example` to `.env`.
2. Set database credentials, `JWT_SECRET`, and `REFRESH_TOKEN_KEY`. For Aiven MySQL, set `DB_SSL_CA` to the downloaded CA certificate path and keep `DB_SSL_VERIFY=true`.
3. Run migrations:

   ```bash
   php console/cli.php migration
   ```

4. Start the API:

   ```bash
   php -S 127.0.0.1:8000 -t public
   ```

The base API URL is `http://127.0.0.1:8000/api`.

## Production deployment

Deploy this repository as a standalone PHP service. Provide the following environment variables in the Render service:

- `DB_DRIVER`
- `DB_HOST`
- `DB_PORT`
- `DB_USER`
- `DB_PASSWORD`
- `DB_NAME`
- `DB_CHARSET`
- `DB_PREFIX`
- `DB_SSL_CA`
- `DB_SSL_VERIFY`
- `API_ALLOWED_ORIGIN` (the exact frontend Static Site origin, e.g. `https://arandia-ruine-frontend.onrender.com`)
- `JWT_SECRET`
- `REFRESH_TOKEN_KEY`

For Aiven, provide the CA certificate to the service securely and configure `DB_SSL_CA` to its mounted path. Keep `DB_SSL_VERIFY=true`; do not commit database credentials or certificates.

Set the Render start command to:

```bash
php -S 0.0.0.0:$PORT -t public
```
