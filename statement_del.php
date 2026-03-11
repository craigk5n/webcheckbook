<?php
declare(strict_types=1);

include_once 'includes/config.php';
include_once 'includes/php-dbi.php';
include_once 'includes/functions.php';
include_once 'includes/connect.php';
include_once 'includes/ui.php';
include_once 'includes/translate.php';

$acct = getIntValue('acct');
$statement = getIntValue('statement');
if (empty($acct))
    fatalError('No account specified');
if (empty($statement))
    fatalError('No statement specified');

$Account = get_account_info($acct);

$error = '';

// Delete transactions
$res = dbi_execute(
    'DELETE FROM chk_bank_trans WHERE chk_acct_id = ? AND chk_statement_id = ?',
    [$acct, $statement]
);
if (!$res) {
    $error = 'Error deleting bank transactions: ' . dbi_error();
}

// Delete statement
if (empty($error)) {
    $res = dbi_execute(
        'DELETE FROM chk_bank_statement WHERE chk_acct_id = ? AND chk_statement_id = ?',
        [$acct, $statement]
    );
    if (!$res) {
        $error = 'Error deleting statement: ' . dbi_error();
    }
}

if (!empty($error)) {
    print_header('Delete Statement Results');
    print_heading('Delete Statement Results');
    echo "<h2>Error</h2>\n" . htmlentities($error) . "\n";
    print_trailer();
} else {
    do_redirect('statements.php?acct=' . $acct);
}
