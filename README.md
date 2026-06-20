# Daybook

A personal, single-user project tracker: a spreadsheet-like grid with inline
autosave editing, per-project category/priority lists, drag-to-reorder
within a priority, docs links, and threaded notes per item.

## Stack

Plain PHP (PDO/MySQL) + vanilla JS. No build step, no framework — upload and go.

## Local development (Docker)

A single container runs Apache+PHP and MariaDB (MySQL-compatible) together via
`supervisord`, so you can run the whole app locally with one `docker run`.
This is dev-only - it's not what GreenGeeks uses in production.

```sh
docker build -t daybook-dev -f docker/Dockerfile .
docker volume create daybook_mysql_data
docker run -d --name daybook-dev \
  -p 8765:80 -p 3307:3306 \
  -v "$(pwd):/var/www/html" \
  -v daybook_mysql_data:/var/lib/mysql \
  daybook-dev
```

- On first boot it creates the `daybook` database/user and imports `sql/schema.sql`
  automatically; on later boots it just starts both services (your data persists in
  the `daybook_mysql_data` volume).
- Visit `http://127.0.0.1:8765/login.php`. Create `includes/config.local.php`
  (gitignored) pointing at `host: 127.0.0.1`, `name/user/pass: daybook`, plus an
  `auth.password_hash` (see step 5 below for how to generate one) - this file
  is read in place of `includes/config.php` when present.
- The repo is bind-mounted into the container, so editing files on your host
  is picked up immediately - no rebuild needed unless you change the Dockerfile.
- MariaDB is also reachable from your host at `127.0.0.1:3307` if you want to
  poke at it with a GUI client.

## Deploying to GreenGeeks (cPanel)

1. **Create the subdomain.** In cPanel > Domains, create `subdomain.myremotecode.com`
   pointing at a document root, e.g. `~/subdomain.myremotecode.com`.
2. **Create a MySQL database.** In cPanel > MySQL Databases, create a database and
   a user with full privileges on it. Note the host (usually `localhost`), db name,
   username, and password.
3. **Import the schema.** Open phpMyAdmin, select the new database, and run the
   contents of [`sql/schema.sql`](sql/schema.sql) (Import or SQL tab). This creates
   the tables and seeds default categories/priorities/statuses for a "General" project.
4. **Upload the app files.** Upload everything in this repo into the subdomain's
   document root (via File Manager, FTP, or git deploy). The folder structure
   (`index.php`, `api/`, `includes/`, `assets/`, etc.) should sit directly in the
   document root.
5. **Configure credentials.** Copy `includes/config.php` to `includes/config.local.php`
   (gitignored, won't be overwritten by future deploys) and fill in:
   - `db.host` / `db.name` / `db.user` / `db.pass` from step 2
   - `auth.password_hash` — generate with:
     ```
     php -r "echo password_hash('your-password', PASSWORD_DEFAULT);"
     ```
     If you don't have shell access on the server, run that locally (any PHP CLI)
     and paste the resulting hash in.
6. **Visit the site** at `https://subdomain.myremotecode.com/login.php` and log in
   with the password you hashed above.

## Notes on the data model

- **Projects** are top-level groupings. Each project gets its own **Category** and
  **Priority** lists (seeded from the same defaults, editable independently per
  project via the "Edit Categories" / "Edit Priorities" buttons).
- **Statuses** (TODO/UNDERWAY/OK/N/A/BLOCKED/...) are shared globally and editable
  via "Edit Statuses".
- The **`#`** column is the global add-order (next item always gets the highest
  number across the whole list). **Order** is the position within a Priority bucket
  and is drag-to-reorder once you click "Sort by Priority > Order" to switch into
  grouped view.
- **Docs** and **Notes** are managed per item via the "Docs" / "Notes" buttons,
  which open a detail panel (multiple doc links, a running list of timestamped,
  individually editable/deletable notes).
