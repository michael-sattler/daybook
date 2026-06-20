
*////////////// Front-End Standards //////////////*

*htaccess and ROUTING*
All official URI paths for admin, public app, and API should be routed rather than use the default system. Default routing is fine on development while files are being tested

*3RD PARTY LIBRARIES*
Bootstrap (latest)
Fontawesome (latest)
Google Fonts
Google Analytics (via Google Tag Manager)

*JAVASCRIPT AND API CALLS*
- Tools should be predominantly php with javascript and light AJAX functionality for specific needs. 
- Javascript used in files should be stored centrally as js files for reusability
- API endpoint files should be stored separately and accessible through reusable endpoints. We shold avoid file-specific AJAX except within admin files
- routing in the /api directory will direct traffic to the php endpoints as rational paths like /api/[entity]  without the .php suffix
- API security will be simple to start - token between client and server and locked down to requests from authorized URLs

- Each app page should inclued a universal listener
- input santization: 
  - 1. No escaping during form processing
  - 2. SQL escape only in database functions ($character_name = mysqli_real_escape_string($mysqli, $character_name);)
  - 3. HTML escape only during output (echo htmlspecialchars($character_name);)
- PHP/web app files should use ob_start() and a centralized layout file to centralize display templates:
  - Page scripts (`index.php`, `login.php`, etc.) do their auth/data setup, then `ob_start()`, echo only their page-specific body markup, then `$content = ob_get_clean();` and `include` the layout.
  - `/elements/layout.php` - shell for authenticated app pages (doctype/head/body, includes `/elements/topbar.php`, echoes `$content`, then `$pageScripts` for page-specific `<script>` tags).
  - `/elements/layout-public.php` - shell for unauthenticated pages (doctype/head/body, no topbar, echoes `$content`). Supports `$bodyClass` for page-specific body classes (e.g. `login-page`).
  - `/elements/pagehead.php` - shared `<head>` (meta, title via `$pageTitle ?? '[App Name]'`, global CSS/JS includes), pulled in by both layouts.
  - `/elements/topbar.php` - shared top nav/header chrome for authenticated pages.
  - Reusable display fragments belong in `/elements/`; page scripts stay thin (data + one `ob_start()`/layout include).

*STYLES*
- Front-end styles should be in /public/assets/css/style.css and on application-specific /public/assets/css/style-[application].css if not needed in most places. (`/public/assets/` holds front-end-served files - css/js/images; `/public/app/` is reserved for backend includes per specs-architecture.md, so frontend assets stay separate from it.)
- we can add page-specific styles inline on the page during development, but should come back to clean those up later
- All on-screen Javascript AND PHP error messages for users and admins should be stored in $_SESSION['error_message'], $_SESSION['success_message'], or $_SESSION['info_message'], which will be shown to the user via a centralized alert system

