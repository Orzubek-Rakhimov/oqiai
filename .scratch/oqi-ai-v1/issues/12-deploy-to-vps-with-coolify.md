# 12: Deploy to the VPS with Coolify

**What to build:** Make the app deployable to the owner's own VPS through Coolify, following the setup of the owner's ITPlaza CRM project (`D:\dasturlash\itplazanew`; read its `docs/deployment.md`, `Dockerfile`, `compose.production.yaml`, `docker/` and `.github/workflows/tests.yml`). The result is an HTTPS link that opens on phones, so the browser microphone can be tested early on Android Chrome and iPhone Safari. Later tickets ship the same way. The clicks in Coolify and GitHub (creating resources, domain, secrets) are the owner's; this ticket delivers everything in the repository and the guide for those clicks.

**Blocked by:** 01

**Status:** ready-for-agent

- [ ] A `Dockerfile` with an `app` image (PHP-FPM with the app and its built frontend) and a `web` image (nginx serving `public/` on port 8080), modelled on ITPlaza's. Exact versions are pinned, and the PHP extensions match what this app needs.
- [ ] A `compose.production.yaml` with two services: the PHP service, named for this project alone (not `app`), and nginx. There's no scheduler, because nothing runs on a schedule yet. Settings come from environment variables, with production mode, debug off and HTTPS-only cookies fixed. A volume holds `storage/app`, so the saved voice clips outlive redeploys. Nginx serves them from `/storage`.
- [ ] A start-up script that refuses unsafe settings (missing APP_KEY, non-HTTPS APP_URL), caches config, routes, events and views, runs the migrations, and only then marks the PHP service ready.
- [ ] Production uses **SQLite** (owner's decision: this is a test project), unlike ITPlaza's PostgreSQL. The database file lives on its own persistent volume and is created on first start if missing. SQLite is set up for a small web app (WAL journal mode, busy timeout, foreign keys). Any safety copy before migrations is a consistent copy of the file, not a raw copy while it's in use. The guide tells the owner to back up the database volume.
- [ ] `.env.production.example` lists every setting Coolify needs, with no secrets. The speech and voice service keys from ticket 13 are listed as empty placeholders.
- [ ] The GitHub Actions workflow runs the checks on `main` and, once they pass, calls Coolify's deploy webhook. It skips the deploy step when the webhook secrets aren't set.
- [ ] `docs/deployment.md` walks the owner through the first setup and every later deploy, in the same style as ITPlaza's guide: GitHub repo, app resource from the compose file (no database resource: SQLite lives on a volume), domain on nginx port 8080, environment variables, first deploy, check `/up`, deploy webhook.
- [ ] The images build locally if Docker is available. If it isn't, say so in the report rather than claiming they build.
