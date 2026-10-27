# web/: Laravel + Inertia + React + Tailwind

The main web application: Laravel 13 on PHP 8.3+, with React 19 pages rendered through
Inertia.js and styled with Tailwind CSS 4. Data is stored in MySQL 8.

## Requirements

| Tool | Version | macOS install |
|---|---|---|
| PHP | 8.3 or newer | `brew install php` |
| Composer | 2.x | `brew install composer` |
| MySQL | 8.x (not MariaDB) | `brew install mysql@8.4` then `brew services start mysql@8.4` |
| Node.js | 20.19 or newer | `brew install node` |

`mysql@8.4` is keg-only, so add its client to your PATH (for example in `~/.zshrc`):

```sh
export PATH="/opt/homebrew/opt/mysql@8.4/bin:$PATH"
```

## First-time setup

1. Create the databases and a local user (one time):

   ```sh
   mysql -u root <<'SQL'
   CREATE DATABASE shariah_p2p CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   CREATE DATABASE shariah_p2p_testing CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   CREATE USER 'shariah_p2p'@'localhost' IDENTIFIED BY 'choose-a-local-password';
   GRANT ALL PRIVILEGES ON shariah_p2p.* TO 'shariah_p2p'@'localhost';
   GRANT ALL PRIVILEGES ON shariah_p2p_testing.* TO 'shariah_p2p'@'localhost';
   SQL
   ```

2. Install dependencies and configure the app:

   ```sh
   cd web
   composer install
   npm install
   cp .env.example .env        # then set DB_PASSWORD to the password chosen above
   php artisan key:generate
   php artisan migrate
   ```

## Running locally

```sh
npm run dev            # terminal 1: Vite dev server (hot reload for React/Tailwind)
php artisan serve      # terminal 2: Laravel at http://localhost:8000
```

Or run `npm run build` once and only `php artisan serve`.

## Tests

```sh
php artisan test
```

Tests run against the separate `shariah_p2p_testing` MySQL database (set in `phpunit.xml`),
which is wiped and re-migrated by tests that use `RefreshDatabase`. The development database
is never touched by tests.

## Where things live

| Path | Purpose |
|---|---|
| `routes/web.php` | URL → controller mapping |
| `app/Http/Controllers/` | Controllers; they return `Inertia::render('PageName', [props])` |
| `app/Http/Middleware/HandleInertiaRequests.php` | Inertia middleware; data shared with every page goes in `share()` |
| `resources/views/app.blade.php` | The single HTML shell every page is mounted into |
| `resources/js/app.jsx` | Boots React and maps page names to files in `resources/js/pages/` |
| `resources/js/pages/` | One React component per page (e.g. `Welcome.jsx`) |
| `resources/css/app.css` | Tailwind entry point |
| `tests/Feature/` | HTTP-level tests run by `php artisan test` |
