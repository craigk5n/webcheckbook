# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

WebCheckbook is a self-contained PHP web application for managing personal bank accounts. It supports transaction tracking, bank statement import/reconciliation, search, reports, and data export. No build system, no framework — just PHP served directly with Bootstrap 5 for the frontend.

## Commands

- **Run tests:** `./vendor/bin/phpunit` (68 tests covering date parsing, CSV import, amount handling, input validation)
- **Run single test:** `./vendor/bin/phpunit tests/DateParsingTest.php`
- **Syntax check:** `php -l <file.php>`

## Architecture

**Flat PHP with View + Handler pattern.** Most features consist of:
- A view file (renders form/page): e.g., `add_trans.php`, `edit_trans.php`
- A handler file (processes form submission): e.g., `add_trans_handler.php`, `edit_trans_handler.php`

**Shared includes** in `includes/`:
- `config.php` — DB credentials (`$db_type = "pdo_mysql"`), timezone, app settings
- `connect.php` — DB connection initialization
- `php-dbi.php` — PDO database abstraction layer (functional API wrapping PDO)
- `functions.php` — Core utilities: input parsing, date formatting, balance calculation, transaction matching, CSV parsing, shared helpers (`get_account_info()`, `get_next_trans_id()`, `parse_date_input()`, `parse_csv_headers()`, `parse_csv_row()`)
- `ui.php` — HTML rendering with Bootstrap 5 (navbar, cards, tables, badges)
- `translate.php` — i18n system loading `translations/{Language}.txt` key:value files

**Frontend:**
- `style.css` — Custom styles supplementing Bootstrap 5
- `js/checkbook.js` — Autocomplete, last-amount lookup (fetch API), date helpers

**Entry point:** `index.php` redirects to `accounts.php`.

## Database

4 tables defined in `tables-mysql.sql`:
- **chk_account** — Bank accounts with running balance and reconciled balance
- **chk_trans** — User-entered transactions (types: 1=Deposit, 2=Debit, 3=Check, 4=Fee)
- **chk_bank_statement** — Imported bank statement metadata
- **chk_bank_trans** — Imported bank transaction lines, linked to user transactions after reconciliation

Dates stored as YYYYMMDD integers. Amounts are FLOAT (positive=deposit, negative=withdrawal).

## Key Conventions

- **SQL safety:** Always use `dbi_execute($sql, [$params])` with parameterized queries, never string interpolation
- **Account loading:** Use `get_account_info($acctId)` helper instead of duplicating the query
- **Date parsing:** Use `parse_date_input($str)` for user-entered dates; returns YYYYMMDD or empty string
- **CSV parsing:** Use `parse_csv_headers()`, `validate_csv_headers()`, `parse_csv_row()` — all testable without DB
- **Input handling:** Use `getPostValue()`, `getGetValue()`, `getIntValue()` from `functions.php`
- **Output escaping:** Use `htmlentities()` / `htmlspecialchars()` for all user data rendered in HTML
- **PHP 8 strict types:** Files use `declare(strict_types=1)` and typed function signatures
- **Translation:** Use `translate('key')` / `etranslate('key')` for UI strings
- **No authentication built-in** — relies on `.htaccess` or reverse proxy

## Testing

PHPUnit tests in `tests/` with a bootstrap that stubs `translate()` and `fatalError()` to allow testing without DB:
- `DateParsingTest.php` — `parse_date_input()`, `date_to_str()`, `shiftDate()`
- `CsvParsingTest.php` — `parse_csv_headers()`, `validate_csv_headers()`, `parse_csv_row()`, full pipeline
- `AmountHandlingTest.php` — `determine_transaction_type()`, `format_amount()`, sign conventions
- `InputValidationTest.php` — `getValue()`, `getIntValue()`, SQL injection rejection

## Setup

1. Import `tables-mysql.sql` into MySQL/MariaDB
2. Copy `config-sample.php` to `includes/config.php` and set DB credentials (use `$db_type = "pdo_mysql"`)
3. `composer install` for PHPUnit dev dependencies
4. Serve from any PHP 8.0+ web server
