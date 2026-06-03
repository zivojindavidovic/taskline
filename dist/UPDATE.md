# Taskline — procedura za update (nova verzija na produkciji)

Kompletna procedura kada se naprave izmene u kodu: kako se bilduje i objavljuje
novi Docker image i šta se radi na serveru. Sve komande pokrećeš ručno.

> Detalji prve instalacije i referenca za websocket env varijable su u
> [`DEPLOY.md`](DEPLOY.md). Ovaj fajl pokriva samo update postojeće instalacije.

---

## 1. Lokalno — build i push novog image-a

### 1.1. Commit-uj izmene

Build skripta taguje image i sa `:latest` i sa `:<git-sha>` (kratki hash
HEAD commit-a). Zato prvo commit-uj, da `<git-sha>` tag odgovara onome što
stvarno ide na produkciju:

```bash
git add -A
git commit -m "opis izmene"
```

### 1.2. Docker login (samo prvi put)

```bash
docker login
```

### 1.3. Build + push

Iz root-a projekta (skripta sama rešava putanje):

```bash
./dist/build-and-push.sh
```

Šta skripta radi:
- bilduje **multi-arch** image (`linux/amd64` + `linux/arm64`) preko `docker buildx`
  — radi i na Intel i na ARM serverima (tvoj Hetzner server je ARM);
- frontend (`npm run build`) i `vendor` (composer) se bilduju **unutar image-a** —
  ne treba ništa ručno da bilduješ pre toga;
- push-uje `zivojindavidovic/taskline:latest` **i** `zivojindavidovic/taskline:<git-sha>`.

Korisne varijante:

```bash
TAG=1.2.0 ./dist/build-and-push.sh             # dodatni, ručni tag umesto latest
PLATFORM=linux/arm64 ./dist/build-and-push.sh  # brži build, samo ARM (samo ARM server može da povuče!)
IMAGE=drugiuser/taskline ./dist/build-and-push.sh
```

> amd64 polovina builda ide kroz emulaciju na Mac-u, pa pun multi-arch build
> traje duže (kompajliraju se PHP ekstenzije). To je normalno.

---

## 2. Na serveru — povlačenje nove verzije

SSH na server, pa u folder gde stoji compose stack:

```bash
cd /opt/taskline-app
docker compose pull
docker compose up -d
```

To je sve. Prilikom starta `app` kontejner automatski (vidi `entrypoint.sh`):
- sačeka da baza bude dostupna;
- pokrene **migracije** (`php artisan migrate --force`) — samo `app` kontejner
  (`TASKLINE_INIT=1`), pa se `reverb` ne sudara sa njim;
- sinhronizuje novi `public/` (frontend assets) u shared volume koji nginx servira —
  novi JS/CSS se servira odmah;
- rebuild-uje config/view cache (pa se primene i izmene iz `.env`).

---

## 3. Provera da je sve prošlo

```bash
docker compose ps                  # svi servisi "running"
docker compose logs -f app         # vidiš migracije i boot; Ctrl+C za izlaz
docker compose logs -f reverb      # websocket server podignut na :8081
```

U browseru otvori aplikaciju i uradi **hard refresh** (Ctrl+Shift+R /
Cmd+Shift+R) da povučeš nove assets. Proveri da realtime radi (otvori projekat
u dva browsera i pomeri task).

Koja verzija image-a trenutno radi:

```bash
docker compose images app
```

---

## 4. Rollback (vraćanje na prethodnu verziju)

Svaki build je push-ovan i kao `:<git-sha>`, pa se možeš vratiti na bilo koju
raniju verziju. U `/opt/taskline-app/.env` na serveru dodaj (ili izmeni):

```bash
TASKLINE_IMAGE=zivojindavidovic/taskline:<stari-git-sha>
```

pa:

```bash
docker compose up -d
```

(`docker-compose.yml` koristi `${TASKLINE_IMAGE:-zivojindavidovic/taskline:latest}`,
pa kad varijabla nije postavljena, ide `latest`.)

> Pažnja: migracije se ne vraćaju same — rollback koda je bezbedan samo ako
> nova verzija nije menjala šemu baze, ili ako su migracije kompatibilne unazad.

---

## 5. Česte situacije

| Situacija | Šta uraditi |
|-----------|-------------|
| Menjao si samo `.env` na serveru | `docker compose up -d` (restart je dovoljan — config cache se pravi na startu) |
| Pull javi "no matching manifest for linux/arm64" | Image je bildovan samo za amd64 — ponovi build bez `PLATFORM` override-a (default je multi-arch) |
| Stari JS/CSS u browseru posle update-a | Hard refresh; assets se sinhronizuju na startu `app` kontejnera, proveri `docker compose logs app` |
| Treba ti čist restart bez nove verzije | `docker compose restart` |
| Provera prostora na serveru | `docker system df`, čišćenje starih image-a: `docker image prune -a` |
