# Taskline — backup & restore

Backups are **your responsibility** as the operator of a self-hosted instance.
This document tells you exactly what to back up, how, and how to restore it.

All commands are run from the folder containing `docker-compose.yml` (the
`dist/` folder you copied to the server).

---

## What needs backing up

| What | Where | How |
|------|-------|-----|
| Database | `postgres` service (`taskline-db` volume) | `pg_dump` (see below) — **not** a raw volume copy |
| App storage | `taskline-storage` volume | tar archive (see below) |
| Environment | `.env` next to `docker-compose.yml` | plain file copy |

> **⚠️ Don't skip the storage volume.** If you left `APP_KEY` empty in `.env`,
> the key was auto-generated on first start and lives **only** in
> `storage/app/.appkey` inside the storage volume. Lose it and everything
> Laravel encrypted with it is gone for good. The volume also holds uploaded
> files and logs.

What you do **not** need to back up:

- `taskline-public` volume — rebuilt from the image on every container start.
- The raw `taskline-db` volume — a `pg_dump` is consistent, smaller, and
  restores across Postgres versions; a raw copy is only valid if the stack is
  stopped and ties you to the same Postgres major version.

---

## Manual backup

### 1. Database

```bash
docker compose exec -T postgres sh -c 'pg_dump -U "$POSTGRES_USER" -Fc "$POSTGRES_DB"' \
  > taskline-db-$(date +%F).dump
```

This uses the credentials already inside the container, so it works regardless
of what you set in `.env`. The `-Fc` (custom) format is compressed and lets
`pg_restore` do clean, selective restores.

### 2. Storage volume

```bash
docker compose exec -T app tar czf - -C /var/www/html storage \
  > taskline-storage-$(date +%F).tar.gz
```

### 3. `.env`

```bash
cp .env taskline-env-$(date +%F).backup
```

Then **copy all three files off the server** (rsync, rclone, S3, anywhere that
isn't the same machine). A backup that lives next to the data it protects only
covers you against `DROP TABLE`, not against losing the server.

### Scheduled backups with cron

```cron
# /etc/cron.d/taskline-backup — daily at 03:00, keep what you copy off-site
0 3 * * * root cd /opt/taskline-app && docker compose exec -T postgres sh -c 'pg_dump -U "$POSTGRES_USER" -Fc "$POSTGRES_DB"' > /opt/taskline-backups/taskline-db-$(date +\%F).dump
15 3 * * * root cd /opt/taskline-app && docker compose exec -T app tar czf - -C /var/www/html storage > /opt/taskline-backups/taskline-storage-$(date +\%F).tar.gz
```

(Note the escaped `\%` — `%` is special in crontab.) Adjust paths to your
install location and add your own off-site copy + rotation.

---

## Automatic database backups (optional sidecar)

If you'd rather not manage cron, add this service to `docker-compose.yml`.
It runs a daily `pg_dump` into `./backups/` with built-in rotation:

```yaml
  db-backup:
    image: prodrigestivill/postgres-backup-local:18   # match the postgres major version
    restart: unless-stopped
    volumes:
      - ./backups:/backups
    environment:
      POSTGRES_HOST: postgres
      POSTGRES_DB: ${DB_DATABASE:-taskline}
      POSTGRES_USER: ${DB_USERNAME:-taskline}
      POSTGRES_PASSWORD: ${DB_PASSWORD:-taskline}
      SCHEDULE: "@daily"
      BACKUP_KEEP_DAYS: 7
      BACKUP_KEEP_WEEKS: 4
      BACKUP_KEEP_MONTHS: 6
    depends_on:
      postgres:
        condition: service_healthy
    networks: [taskline]
```

This covers the **database only** — you still need to back up the storage
volume and `.env` (cron section above), and you still need to copy `./backups/`
off the server.

---

## Restore

### Database (existing instance)

Stop the app first so nothing writes to the database during the restore:

```bash
docker compose stop app reverb

docker compose exec -T postgres sh -c 'pg_restore -U "$POSTGRES_USER" -d "$POSTGRES_DB" --clean --if-exists' \
  < taskline-db-2026-06-03.dump

docker compose up -d
```

### Storage volume

```bash
docker compose exec -T app tar xzf - -C /var/www/html < taskline-storage-2026-06-03.tar.gz
docker compose restart app reverb   # entrypoint fixes ownership/permissions on start
```

### Full disaster recovery (fresh server)

1. Copy the `dist/` folder, your `.env` backup (as `.env`), the database dump
   and the storage tar to the new server.
2. Start only the database and wait for it to be healthy:
   ```bash
   docker compose up -d postgres
   docker compose ps   # wait until postgres is "healthy"
   ```
3. Restore the dump:
   ```bash
   docker compose exec -T postgres sh -c 'pg_restore -U "$POSTGRES_USER" -d "$POSTGRES_DB" --clean --if-exists' \
     < taskline-db-2026-06-03.dump
   ```
4. Start the rest of the stack (migrations are a no-op — the `migrations`
   table came with the dump):
   ```bash
   docker compose up -d
   ```
5. Restore the storage volume:
   ```bash
   docker compose exec -T app tar xzf - -C /var/www/html < taskline-storage-2026-06-03.tar.gz
   docker compose restart app reverb
   ```
6. Open the app and log in. Done.

---

## Test your backups

A backup you've never restored is a hope, not a backup. Every few months, run
the disaster-recovery steps against a scratch VM (or a second compose project
with a different `HTTP_PORT`) and check that you can log in and see your data.
