# Cut Tracker

Personal fitness cut tracker PWA. Log daily nutrition, training, sleep, and bodyweight in under 60 seconds. Works offline, installs as a native-like app on phone and desktop.

## Quick Start (local dev)

```bash
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
npm install && npm run build
php artisan serve
```

Visit http://localhost:8000. No password is set until you configure `APP_PASSWORD_HASH`.

## Setting the Password

Generate a bcrypt hash:

```bash
php artisan app:hash-password
```

Copy the output into `.env`:

```
APP_PASSWORD_HASH=$2y$12$...
```

## Running Tests

```bash
php artisan test --compact
```

## Deployment (Docker)

Build:

```bash
docker build -t cut-tracker:latest .
```

Run locally with a persistent volume:

```bash
docker run -p 8080:8080 \
  -e APP_KEY="base64:..." \
  -e APP_PASSWORD_HASH='$2y$12$...' \
  -e APP_URL="http://localhost:8080" \
  -v cut-data:/var/www/html/database/sqlite \
  cut-tracker:latest
```

## Kubernetes Deployment

1. Copy `k8s/secret.yaml.example` → `k8s/secret.yaml`, fill in values.
2. Apply:

```bash
kubectl apply -f k8s/secret.yaml
kubectl apply -k k8s/
```

3. Update the host in `k8s/ingress.yaml` to your domain.

## Backup

Copy the SQLite file from the running pod:

```bash
kubectl cp <pod-name>:/var/www/html/database/sqlite/cut.sqlite \
  ./cut-backup-$(date +%Y%m%d).sqlite
```

## Export

Click **Export** in the app to download a JSON file with all days and settings for analysis.
