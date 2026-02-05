README.md
========

# WebCheckbook

WebCheckbook is a self-contained PHP web application for tracking and reconciling bank accounts (initially targeted at checkbooks but adaptable to other accounts). It supports features like adding/editing transactions, reconciling with bank statements, searching, exporting data, and generating reports. The app uses a MySQL database backend and is designed to run on a standard LAMP stack (or similar).

## Features
- Manage multiple accounts
- Add, edit, and delete transactions (deposits, debits, checks, fees)
- Reconcile transactions with imported bank statements
- Search and filter transactions by date, description, or reconciliation status
- Export data in text, HTML, or XLS formats
- Generate reports on duplicates, missing checks, unreconciled items, and mismatched amounts
- Autocomplete for transaction descriptions based on history
- Basic UI with tables and forms for mobile-friendly browsing

## Requirements
- PHP 8.0+ (tested with PHP 8.x; uses best practices for security and compatibility)
- MySQL 5.7+ (or compatible, like MariaDB)
- Web server (e.g., Apache or Nginx) with PHP support
- No external dependencies (self-contained; no Composer or autoloading required)
- Browser support: Modern browsers (including mobile); tested on Chrome, Firefox, Safari

## Installation
1. **Clone the Repository**:
   ```
   git clone https://github.com/craigk5n/webcheckbook.git
   cd webcheckbook
   ```

2. **Set Up the Database**:
   - Create a MySQL database (e.g., `checkbook`).
   - Import the schema from `tables-mysql.sql`:
     ```
     mysql -u youruser -p checkbook < tables-mysql.sql
     ```
   - Update database credentials in `includes/config.php` (e.g., host, username, password, database name).

3. **Configure the Web Server**:
   - Place the files in your web server's document root (e.g., `/var/www/html/webcheckbook`).
   - Ensure the web server has write permissions if needed (e.g., for logs, but this app doesn't generate files by default).
   - Point your browser to `http://localhost/webcheckbook/accounts.php` to start.

4. **Add Your First Account**:
   - Navigate to the app and create an account via the UI (or manually insert into `chk_account` table).

## Usage
- **Home Page**: List accounts at `accounts.php`.
- **Add Transactions**: Use `add_trans.php?acct=<id>` for bulk or single additions.
- **Reconcile**: Upload/import bank statements and use `reconcile.php` to match transactions.
- **Search/Export/Reports**: Available via dedicated pages like `search.php`, `export.php`, `report.php`.
- **Admin Tasks**: Update balances with `update_balances.php`; normalize descriptions with `update_transactions.php`.

For detailed usage, refer to the in-app headers or explore the source code.

## Security Notes
- This app uses prepared statements (via `dbi_execute`) to prevent SQL injection.
- Sanitize inputs as needed; the app uses `htmlentities` and `getValue`/`getIntValue` for basic escaping.
- Deploy with HTTPS in production.
- No user authentication is built-in—add it (e.g., via .htaccess or PHP sessions) for multi-user scenarios.

## Contributing
Contributions are welcome! Please follow these steps:
1. Fork the repo.
2. Create a feature branch (`git checkout -b feature/YourFeature`).
3. Commit changes (`git commit -m 'Add YourFeature'`).
4. Push to the branch (`git push origin feature/YourFeature`).
5. Open a Pull Request.

See `CONTRIBUTING.md` for more details.

## License
This project is licensed under the MIT License. See `LICENSE` for details.

## Acknowledgments
- Built with plain PHP for simplicity and self-containment.
- Icons/images (not included in repo) can be added from free sources like Font Awesome.

If you encounter issues, open a GitHub issue!

