# Deploying O'qi AI

O'qi AI runs on the owner's VPS, deployed by Coolify as its own project, the
same way as the ITPlaza CRM. A commit on `main` in the GitHub repository goes
live once its checks pass. Coolify's proxy provides HTTPS, which the browser
needs before it lets a page use the microphone, so the link opens on Android
Chrome and iPhone Safari as it is.

## What runs

Coolify builds `compose.production.yaml`, which has two services from the two
images of the one `Dockerfile` (`app` and `web`):

| Service   | What it does                                                                                                                                             |
| --------- | -------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `oqi-php` | The app's pages (PHP-FPM). Each time it starts it saves a copy of the database if the new version changes it, runs the migrations, then accepts requests |
| `nginx`   | Receives the domain's traffic from Coolify's proxy on port 8080. Serves the JavaScript, CSS, fonts and voice clips; passes the rest to `oqi-php`         |

Unlike ITPlaza, there is **no database server**. v1 is a test with a handful
of families, so the database is a single SQLite file on a volume, as in
development. It is set up for several PHP workers sharing the file (WAL
journal, a 5-second wait when the file is busy, foreign keys on). Sessions and
the cache are kept in it too.

There is no scheduler, no queue worker and no Redis: nothing runs on a
schedule or in the background yet. Pages are drawn in the browser (no
server-side rendering), so no Node process runs on the server.

Two volumes hold what must outlive a redeploy:

| Volume        | What is in it                                                                                             |
| ------------- | --------------------------------------------------------------------------------------------------------- |
| `database`    | `storage/database`: the SQLite file `database.sqlite` and the last three safety copies (`before-deploy/`) |
| `app-storage` | `storage/app`: the voice clips for Syllable help (`storage/app/public`, served under `/storage`)          |

Children's recordings are never saved, on the server or anywhere else
(ADR-0002), so they are in neither volume.

## First setup

### 1. The code on GitHub

1. Create a **private** repository on GitHub.
2. On your PC: `git remote add origin <the repository's address>`, then
   `git push -u origin main`.

### 2. The app

1. In Coolify, in a new project for O'qi AI: **New resource → your private
   GitHub repository → Docker Compose**, branch `main`, and set **Docker
   Compose Location** to `/compose.production.yaml`. No database resource is
   needed.
2. Point the domain at the VPS first: an `A` record for it with the VPS's IP
   address, at your domain registrar. Then, if Coolify doesn't list the two
   services yet, click **Reload Compose File**. Under **Domains → Add
   Domain**, fill in:
    - **Service:** `nginx` (only nginx gets a domain)
    - **Protocol:** `https`
    - **Domain:** the address, without `https://`, e.g. `oqi.example.uz`
    - **Port:** `8080` (the port nginx listens on inside its container; the
      public address has no port)
    - **Path:** empty; **Search engine indexing:** not indexable

    Coolify gets its certificate.

3. Under **Environment variables**, fill in the settings from
   `.env.production.example`. The required ones:
    - `APP_KEY`: run `php artisan key:generate --show` on your PC and paste
      the result. Keep a copy somewhere safe.
    - `APP_URL`: the public address from step 2, e.g.
      `https://oqi.example.uz`. It must start with `https://`, or the app
      refuses to start.

    Leave the speech and voice keys (`GEMINI_API_KEY` and the others) empty
    until ticket 13 connects those services.

4. Leave **automatic deployment off** for now (step 5 sets up deploys).
5. **Deploy.** The first build takes several minutes. On the first start
   `oqi-php` creates the empty database file and runs the migrations.

If nginx answers 502 because it cannot find `oqi-php`, open the app
resource's settings and switch on **Connect To Predefined Network**, then
deploy again. That network is shared with every other project on the
Coolify, so the service names nginx looks up must be this project's own:
never rename `oqi-php` to a common name such as `app` (ITPlaza's is
`crm-php`).

### 3. The Stories and their voice clips

Production starts empty. Open the app resource's **Terminal** in Coolify,
choose the `oqi-php` service, and load the Stories from `resources/stories`
(they are in the image):

```bash
php artisan stories:load
php artisan audio:generate
```

Run both again after every deploy that adds or changes a Story file.
`audio:generate` makes the Syllable help clips that are still missing (each
word's syllables read slowly with pauses, and the whole word read slowly) and
keeps the ones that exist. The clips land in the `app-storage` volume and
outlive every redeploy. If one word sounds wrong, make its clips again with
`php artisan audio:generate <word>`. Never run the database seeder on the
server: it creates a login with a known password.

### 4. Check it

- `https://<domain>/up` answers with a page saying the application is up.
- `https://<domain>` opens on a phone, and a Parent can sign up and log in.
- On Android Chrome and on iPhone Safari, the browser asks for the
  microphone when the app wants it. (It never asks on a page without HTTPS.)
- In Coolify's logs, `oqi-php` shows the database being created and the
  migrations running.

### 5. Deploys from GitHub

The checks in `.github/workflows/tests.yml` run on every commit on `main`;
when they pass, the workflow asks Coolify to deploy.

1. In Coolify, create an API token (**Keys & Tokens → API tokens**; API
   access may first need switching on in Coolify's settings).
2. On the app resource's **Webhooks** page, copy the **deploy webhook**
   address.
3. On GitHub, under the repository's **Settings → Secrets and variables →
   Actions**, add two secrets: `COOLIFY_DEPLOY_WEBHOOK` (the address) and
   `COOLIFY_API_TOKEN` (the token).

Until the secrets exist the workflow only runs the checks. To deploy without
waiting for them, press **Deploy** in Coolify.

## Every deploy after that

Commit to `main` and push. When the checks pass, Coolify rebuilds the images
and replaces the services; the app is unreachable for some seconds while
`oqi-php` restarts. `oqi-php` then:

1. refuses to start if `APP_KEY` is missing or `APP_URL` isn't HTTPS;
2. saves a copy of the database, **if** the new version brings a migration
   (`php artisan backup:before-deploy`), keeping the last three. The copy is
   made with SQLite's `VACUUM INTO`, so it is consistent even while the old
   version is still using the file;
3. runs the new migrations;
4. accepts requests, and `nginx` starts.

If the copy cannot be made, nothing is changed and `oqi-php` does not start:
read its log in Coolify, fix the cause (usually a full disk), and deploy
again. If a migration fails, `oqi-php` does not become ready either; read the
log, fix it, and deploy again.

## Undoing a deploy that damaged the data

The safety copy is the database as it was just before that deploy. Anything
saved after it is lost by restoring, so act quickly.

1. Take the bad version back: `git revert <its commit>` and push (or deploy
   the commit before it in Coolify). Wait until it runs.
2. In Coolify's **Terminal**, on the `oqi-php` service:

    ```bash
    php artisan down
    cd storage/database
    ls -l before-deploy
    cp before-deploy/<the newest file> database.sqlite
    rm -f database.sqlite-wal database.sqlite-shm
    cd /var/www/html
    php artisan up
    ```

    `php artisan down` stops the pages from using the database, and the
    `-wal` and `-shm` files belong to the damaged database, so they must go
    with it.

3. Everyone logs in again and checks the last things they did.

## Backups

Coolify's **Backups** tab is for database resources only, so it does not
cover the SQLite file. Two volumes must be backed up to storage outside the
server, by hand or with the VPS provider's snapshots:

- **`database`**: the SQLite file and its safety copies. This is the one that
  matters: Parents, Children and every Reading.
- **`app-storage`**: the voice clips. They can also be made again with the
  `audio:generate` command, but that costs time and money with the voice service, and
  each clip was checked by ear.

To take a consistent copy of the database by hand (never copy
`database.sqlite` with `cp` while the app is running), run this in the
`oqi-php` service's terminal, then download the file from the server:

```bash
php -r '(new PDO("sqlite:storage/database/database.sqlite"))->exec("VACUUM INTO '\''storage/database/copy-".date("Y-m-d-His").".sqlite'\''");'
```

Also keep, outside the server: the `APP_KEY`. Coolify's own settings have
their own backup (**Settings → Backup**).

### Restoring a backup

1. Put the database copy back as `database.sqlite` in the `database` volume,
   as in "Undoing a deploy" above.
2. Put the clips back into the `app-storage` volume if they were lost.
3. Deploy the app. Try this once before the test families arrive.

## Moving to another server

1. Create the app resource on the new server's Coolify (step 2) with the
   **same `APP_KEY`**.
2. Stop the app on the old server so nothing more is saved there.
3. Copy the contents of both volumes (`database` and `app-storage`) to the
   new server's volumes of the same names.
4. Deploy, and check it (step 4) on a temporary address or by the IP.
5. Change the domain's `A` record to the new server, update the two GitHub
   secrets (step 5), and remove the old resource once all is well.

## Settings worth knowing

- **`RUN_MIGRATIONS`** (default `true`): set it to `false` for one deploy to
  start without the safety copy and the migrations, e.g. to run a risky
  migration by hand from the `oqi-php` service's terminal. Set it back.
- **`BACKUP_BEFORE_MIGRATIONS`** (default `true`): set it to `false` for one
  deploy to run the migrations without the safety copy. Set it back.
- **`DEPLOY_BACKUPS_TO_KEEP`** (default `3`): how many safety copies stay in
  `storage/database/before-deploy`.
- **`TRUSTED_PROXIES`** (default: Docker's private address ranges): whose
  `X-Forwarded-*` headers the app believes when working out whether a
  request came over HTTPS and from which address. The default fits Coolify.
  The forwarded host is never believed, so links always use the app's own
  address.
- **Memory and CPU limits** per service: `APP_MEMORY` (768M), `WEB_MEMORY`
  (128M), `APP_CPUS` (1) and `WEB_CPUS` (0.5). The app serves up to 10
  requests at once (`docker/php/fpm-pool.conf`); raise that together with
  `APP_MEMORY`.
- `APP_ENV=production`, `APP_DEBUG=false`, the SQLite settings and
  HTTPS-only cookies are fixed in the compose file and can't be changed from
  Coolify.

## Good to know

- **One server only.** SQLite is a file on this server's disk, so the app
  runs as a single `oqi-php` service. Moving to a database server
  (PostgreSQL, as ITPlaza) is the step to take if O'qi AI outgrows the test.
- **Logs** go to Coolify's log view; each service keeps at most 30 MB.
- **A command on the server**, such as `php artisan tinker` or resetting a
  Parent's forgotten password by hand: the `oqi-php` service's terminal in
  Coolify.
- **Secrets** live only in Coolify's environment variables and GitHub's
  secrets. Nothing in the repository or the images holds one.
- **The microphone** is allowed for the app's own pages only (nginx sends
  `Permissions-Policy: microphone=(self)`).
- **A scheduler or queue worker** is not running. If a later version needs
  one, add a service like ITPlaza's `scheduler` with the same image, the
  `database` volume, and the command `php artisan schedule:work` (or
  `queue:work`, and set `QUEUE_CONNECTION` to `database`).
- **Building the images on your PC:** `docker build --target app .` and
  `docker build --target web .` build the two images; the build downloads
  the fonts from Bunny Fonts, so it needs the network.
