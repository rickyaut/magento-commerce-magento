# Magento 2 Docker Image

## Prerequisites

Register an access key at [Adobe Commerce Marketplace](https://commercemarketplace.adobe.com/customer/accessKeys/) and save it in `auth.json`:

```bash
cp auth.sample.json auth.json
# edit auth.json with your Magento marketplace credentials
```

## Build

```bash
podman build \
  --secret id=composer_auth,src=auth.json \
  -f Dockerfile-web \
  -t localhost/ricky/magento-web:latest .
```

The build takes ~10-20 minutes as it downloads Magento and all dependencies via Composer.

## Local dev with podman compose

```bash
podman compose up
```

Magento will be automatically installed on first boot. Subsequent restarts skip setup and go straight to Apache.

## Access

- Store: http://localhost
- Admin: http://localhost/admin (username: `admin`, password: `admin123`)

## Notes

- `init-magento.sh` runs on every container start — it waits for MariaDB and OpenSearch before proceeding
- On first boot it runs `setup:install` and full compile/deploy; subsequent boots skip this if `app/etc/env.php` already has DB config
- A cache config fix is applied on every start to prevent a health check 500 caused by `backend_options` without a `backend` key in `env.php`
