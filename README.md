# WebCheckbook

[![PHP Version](https://img.shields.io/badge/php-%3E%3D8.0-777bb4.svg)](https://www.php.net/)
[![License: MIT](https://img.shields.io/badge/license-MIT-green.svg)](LICENSE)
[![Tests: PHPUnit](https://img.shields.io/badge/tests-PHPUnit%2011-brightgreen.svg)](tests/)

WebCheckbook is a self-contained PHP web application for tracking and reconciling personal bank accounts. It supports adding and editing transactions, reconciling against imported bank statements, searching, reports, and data export. It runs on a standard LAMP (or compatible) stack with no application framework.

## Features

- Manage multiple accounts with running and reconciled balances
- Add, edit, and delete transactions (deposits, debits, checks, fees)
- Import bank statements (CSV) and reconcile against user-entered transactions
- Search and filter by date, description, amount, or reconciliation status
- Export data as text, HTML, or XLS
- Reports: duplicates, missing checks, unreconciled items, mismatched amounts
- Autocomplete and last-amount lookup for recurring payees
- Responsive Bootstrap 5 UI (assets vendored locally, no CDN dependency)
- Internationalization via simple `translations/{Language}.txt` key/value files

## Requirements

- PHP 8.0 or newer
- MySQL 5.7+ or MariaDB 10.3+
- A web server with PHP support (Apache, Nginx, Caddy, etc.)
- [Composer](https://getcomposer.org/) (for running the test suite only)

## Installation

1. **Clone the repository**
   ```bash
   git clone https://github.com/craigk5n/webcheckbook.git
   cd webcheckbook
   ```

2. **Create the database and load the schema**
   ```bash
   mysql -u <user> -p -e "CREATE DATABASE checkbook"
   mysql -u <user> -p checkbook < tables-mysql.sql
   ```

3. **Configure the application**
   ```bash
   cp includes/config-sample.php includes/config.php
   ```
   Edit `includes/config.php` and set the database host, user, password, and name. Leave `$db_type = "pdo_mysql"` (the only supported backend).

4. **Install dev dependencies (optional, for tests)**
   ```bash
   composer install
   ```

5. **Serve the application**
   Point your web server's document root at the project directory and open `http://localhost/accounts.php`. Add your first account through the UI.

## Usage

| Page | Purpose |
|---|---|
| `accounts.php` | List and manage accounts (entry point) |
| `add_trans.php?acct=<id>` | Add one or more transactions |
| `edit_trans.php` | Edit an existing transaction |
| `import.php` | Import a bank statement CSV |
| `reconcile.php` | Match imported bank lines to user transactions |
| `search.php` | Search and filter transactions |
| `report.php` | Duplicate, missing-check, and reconciliation reports |
| `export.php` | Export transactions (TXT / HTML / XLS) |
| `update_balances.php` | Recalculate running balances |
| `update_transactions.php` | Normalize transaction descriptions |

## Testing

The project uses PHPUnit 11. The test suite covers date parsing, CSV import, amount handling, and input validation without requiring a database connection.

```bash
composer install
./vendor/bin/phpunit                           # run all tests
./vendor/bin/phpunit tests/DateParsingTest.php # run a single file
```

You can syntax-check individual PHP files with `php -l <file.php>`.

## Architecture

Flat PHP using a view/handler pattern — most features consist of a view file (e.g. `add_trans.php`) and a matching handler file (e.g. `add_trans_handler.php`). Shared code lives under `includes/`:

- `config.php` — database credentials and app settings (not checked in)
- `connect.php` — database connection bootstrap
- `php-dbi.php` — thin functional wrapper around PDO
- `functions.php` — core utilities (input parsing, date handling, CSV parsing, balance logic)
- `ui.php` — Bootstrap 5 HTML rendering helpers
- `translate.php` — i18n loader

Database schema (`tables-mysql.sql`): `chk_account`, `chk_trans`, `chk_bank_statement`, `chk_bank_trans`.

See [CLAUDE.md](CLAUDE.md) for a deeper architectural overview.

## Security

- All database access uses parameterized queries via `dbi_execute()` (PDO under the hood).
- User-rendered output is escaped with `htmlentities()` / `htmlspecialchars()`.
- Input is validated through typed helpers (`getValue()`, `getIntValue()`, `getPostValue()`, `getGetValue()`).
- **Authentication is not built in.** Protect the application with `.htaccess`, a reverse proxy, or a similar mechanism before exposing it.
- Always deploy behind HTTPS.
- Never commit `includes/config.php` — it contains credentials and is gitignored.

To report a security issue, please open a private GitHub security advisory rather than a public issue.

## Contributing

Contributions are welcome. See [CONTRIBUTING.md](CONTRIBUTING.md) for the workflow. In short:

1. Fork the repository and create a feature branch.
2. Add or update tests for your change.
3. Run `./vendor/bin/phpunit` and ensure everything passes.
4. Open a pull request with a clear description of the change.

## License

Released under the MIT License. See [LICENSE](LICENSE) for the full text.

## Credits

- [Bootstrap 5](https://getbootstrap.com/) for the UI layer (vendored under `vendor/`)
- [PHPUnit](https://phpunit.de/) for the test suite
