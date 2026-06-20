# Daybook

A personal, single-user project tracker: a spreadsheet-like grid with inline
autosave editing, per-project category/priority lists, drag-to-reorder
within a priority, docs links, and threaded notes per item.

## Stack

Plain PHP (PDO/MySQL) + vanilla JS. No build step, no framework — upload and go.

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
