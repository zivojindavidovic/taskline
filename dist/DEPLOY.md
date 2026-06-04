# Taskline — self-hosted deployment

Taskline ships as a single Docker image containing the PHP app, the compiled
frontend (`public/build`) and PHP dependencies (`vendor`). The same image runs
the web app and the Reverb websocket server.

## What's in the box

All deploy files live in `dist/` (paths below are relative to the project root).
The one exception is `.dockerignore`, which must stay at the project root because
it governs the Docker build context.

| File | Purpose |
|------|---------|
| `dist/Dockerfile` | Multi-stage build: composer → frontend (Vite) → php-fpm runtime |
| `dist/entrypoint.sh` | Migrations, storage/asset setup, config cache on start |
| `dist/php/taskline.ini` | Production PHP/OPcache settings |
| `dist/nginx/taskline.conf` | nginx vhost (php-fpm + `/app` Reverb proxy) |
| `dist/docker-compose.yml` | Full self-hosted stack (app, reverb, nginx, postgres) |
| `dist/.env.docker.example` | Environment template |
| `dist/BACKUP.md` | Backup & restore procedure (database, storage, `.env`) |
| `dist/build-and-push.sh` | Multi-arch build (`linux/amd64,linux/arm64`) + push to the registry |
| `.dockerignore` *(project root)* | What gets sent to the build context |

---

## A. Build & publish the image (maintainer)

The frontend is built **inside** the image now — no separate `node` container.
Run from the project root (the script resolves its own paths and uses the
project root as the build context):

```bash
docker login                       # once
IMAGE=YOURUSER/taskline ./dist/build-and-push.sh
```

This builds multi-arch for `linux/amd64,linux/arm64` (works on both Intel and
ARM servers) and pushes `YOURUSER/taskline:latest` plus a `:<git-sha>` tag.
Override the tag with `TAG=1.0.0`, or build a single arch faster with
`PLATFORM=linux/arm64` (only that arch can pull the image then).

> The websocket host/port/scheme are **not** baked into the build — they're
> resolved at runtime, so one image works on any domain or IP.

---

## B. Run it (self-hoster)

On the server, copy the `dist/` folder over, then run everything from inside it
(compose paths and the `.env` are relative to `dist/`):

```bash
cd dist
cp .env.docker.example .env
# edit .env: APP_URL, DB_PASSWORD, REVERB_APP_KEY/SECRET/ID
nano .env

docker compose pull
docker compose up -d
```

Open `http://SERVER_IP` (or your `APP_URL`). The `app` container runs
migrations on first boot; `APP_KEY` is auto-generated and persisted if you
left it blank.

### Things the stack does for you
- **Migrations** run automatically (only on the `app` container).
- **`public/` assets** are synced into a shared volume that nginx serves, so an
  image upgrade ships new assets automatically.
- **Websockets** go `browser → nginx /app → reverb:8081`; PHP publishes events
  to `reverb:8081` over plain HTTP on the internal network.
- **No queue worker** is needed: all broadcast events are `ShouldBroadcastNow`
  (synchronous) and `QUEUE_CONNECTION=sync` runs any future queued work inline.

### Backups

Backups are your responsibility as the operator — the stack does **not** back
anything up for you. What to back up (database, storage volume, `.env`), how,
and how to restore is documented step by step in [`BACKUP.md`](BACKUP.md).
Back up the database before every upgrade.

### Upgrading
```bash
docker compose pull && docker compose up -d
```

The full release procedure (build, push, server steps, rollback) is documented
step by step in [`UPDATE.md`](UPDATE.md).

---

## C. Migrating your existing manual setup (`/opt/...`)

Your current setup mounts the code (`/opt/taskline`) and builds the frontend
with a throwaway `node` container. With the baked image you no longer need
either — the code, `vendor` and `public/build` live in the image.

You can keep your existing separate `nginx` / `postgres` stacks if you prefer;
just point a service at `image: YOURUSER/taskline:latest` instead of building
locally and drop the code volume mount. The included `dist/docker-compose.yml`
is the all-in-one replacement.

> Old `.env.production` with `VITE_REVERB_HOST=<ip>` is obsolete — leave
> `REVERB_FRONTEND_*` blank and the browser figures out the host itself.

---

## Reverb / websocket env reference

| Variable | Value | Used by |
|----------|-------|---------|
| `BROADCAST_CONNECTION` | `reverb` | PHP — without it events go to the log |
| `REVERB_HOST` | `reverb` | PHP → reverb container (internal) |
| `REVERB_PORT` | `8081` | PHP → reverb container |
| `REVERB_SCHEME` | `http` | **must** be http on the internal network |
| `REVERB_APP_KEY/SECRET/ID` | your values | shared by PHP, reverb, browser |
| `REVERB_FRONTEND_HOST/PORT/SCHEME` | *blank* | browser (blank = derive from page) |
