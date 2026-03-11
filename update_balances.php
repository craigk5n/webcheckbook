<?php
declare(strict_types=1);

include_once 'includes/config.php';
include_once 'includes/php-dbi.php';
include_once 'includes/functions.php';
include_once 'includes/connect.php';
include_once 'includes/ui.php';
include_once 'includes/translate.php';

$acct = getIntValue('acct');
if (empty($acct))
    fatalError('No account specified');

$Account = get_account_info($acct);

print_header(translate('Account') . ': ' . $Account['name']);
print_heading(translate('Account') . ': ' . $Account['name']);

update_balances($acct);

print_account_info($Account);

print_trailer();
