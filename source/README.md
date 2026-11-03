# Magento 2 Docker Image

## Prerequisites

Register an access key at [Adobe Commerce Marketplace](https://commercemarketplace.adobe.com/customer/accessKeys/) and save it in `auth.json`:

```bash
cp auth.sample.json auth.json
# edit auth.json with your credentials
```

## Build & Start

```bash
cd source
docker build -f ./Dockerfile-web -t ricky/magento-web \
  --secret id=composer_auth,src=auth.json .
docker compose up
```

Magento will be automatically installed on first boot. Subsequent restarts will skip the setup and go straight to Apache.

## Access

- Store: http://localhost
- Admin: http://localhost/admin/admin (username: `admin`, password: `admin123`)
