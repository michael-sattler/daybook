# Daybook

A personal, single-user project tracker: a spreadsheet-like grid with inline
autosave editing, per-project category/priority lists, drag-to-reorder
within a priority, docs links, and threaded notes per item.

## Stack

Plain PHP (mysqli/MySQL) + vanilla JS. No build step, no framework — upload and go.

## Directory layout

```
public/        <- web document root (set this as the vhost/cPanel docroot)
  index.php, login.php, logout.php, .htaccess
  config/       config.php + auth.php + database.php + platform configs (gitignored)
  app/includes/ shared backend functions
  api/          JSON endpoints
  elements/     layout/header partials
  assets/       css/js/images
docs/           specs (NOT web-accessible)
sql/            schema.sql + migrations (NOT web-accessible)
docker/         local dev container definitions (NOT web-accessible)
```

Only `public/` is ever exposed to the web - `docs/`, `sql/`, and `docker/` stay
outside the document root on every environment.

## Local development (Docker)

`docker-compose` runs three containers: `web` (Apache+PHP), `db` (MariaDB,
MySQL-compatible), and `phpmyadmin`. This is dev-only - GreenGeeks production
uses its own cPanel-provisioned MySQL, not these containers or credentials.

```sh
docker compose up -d --build
```

- **App:** http://localhost:8765
- **phpMyAdmin:** http://localhost:8081
- **Database:** `localhost:3307` (user/pass/db: `daybook`/`daybook`/`daybook`)

On first boot, the `db` container automatically imports [`sql/schema.sql`](sql/schema.sql)
(MariaDB's official image runs anything in `docker-entrypoint-initdb.d` once,
on an empty data directory only). Data persists in the `daybook_mysql_data`
volume across restarts.

Create `public/config/development.config.php` (gitignored) before first run:

```php
<?php
$dbHost = 'db';
$dbName = 'daybook';
$dbUser = 'daybook';
$dbPass = 'daybook';
$authPasswordHash = 'paste the output of the password_hash command below';
```

Generate a password hash with:
```sh
php -r "echo password_hash('your-password', PASSWORD_DEFAULT);"
```

The repo is bind-mounted into `web`, so editing files on your host is picked
up immediately - no rebuild needed unless you change a Dockerfile.

Useful commands:
```sh
docker compose down          # stop
docker compose down -v       # stop + wipe the database volume
docker compose logs -f web   # tail web container logs
docker compose exec db mysql -u daybook -pdaybook daybook
```

## Deploying to GreenGeeks (cPanel)

1. **Create the subdomain.** In cPanel > Domains, create `subdomain.myremotecode.com`
   and set its document root to the `public/` folder of wherever you upload this
   repo, e.g. `~/daybook/public` (not the repo root - `docs/`, `sql/`, and `docker/`
   must stay outside the web-served directory).
2. **Create a MySQL database.** In cPanel > MySQL Databases, create a database and
   a user with full privileges on it. Note the host (usually `localhost`), db name,
   username, and password.
3. **Import the schema.** Open phpMyAdmin, select the new database, and run the
   contents of [`sql/schema.sql`](sql/schema.sql) (Import or SQL tab). This creates
   the tables and seeds default categories/priorities/statuses for a "General" project.
4. **Upload the app files.** Upload the whole repo (e.g. via git deploy or FTP) to
   `~/daybook`, keeping the `public/` subfolder structure intact. Point the
   subdomain's document root at `~/daybook/public` (step 1).
5. **Configure credentials.** Create `public/config/production.config.php`
   (gitignored, won't be overwritten by future deploys):
   ```php
   <?php
   $dbHost = 'localhost';
   $dbName = 'your_db_name';
   $dbUser = 'your_db_user';
   $dbPass = 'your_db_password';
   $authPasswordHash = 'paste the output of the password_hash command below';
   ```
   Generate the hash with `php -r "echo password_hash('your-password', PASSWORD_DEFAULT);"`
   (run locally if you don't have shell access on the server).
6. **Visit the site** at `https://subdomain.myremotecode.com/login` and log in
   with the password you hashed above.

## Notes on the data model

- **Projects** are top-level groupings. Each project gets its own **Category**,
  **Subsystem**, and **Priority** lists (editable independently per project via
  the "Edit Categories" / "Edit Subsystems" / "Edit Priorities" buttons). Projects,
  Subsystems, Priorities, and Statuses all support per-entry background/text
  colors via the paint-bucket/T buttons in their management modals.
- **Statuses** (TODO/UNDERWAY/OK/N/A/BLOCKED/...) are shared globally and editable
  via "Edit Statuses".
- The **`#`** column is the global add-order (next item always gets the highest
  number across the whole list). **Order** is the position within a Priority bucket
  and is drag-to-reorder once you click "Sort by Priority > Order".
- **Docs** and **Notes** are managed per item via the "Docs" / "Notes" buttons,
  which open a detail panel (multiple doc links, a running list of timestamped,
  individually editable/deletable notes).
- Project URLs are bookmarkable at `/projects/{slug}`.
