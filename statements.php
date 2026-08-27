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

print_header(translate('Statements'));
print_heading(translate('Statements'));
print_account_info($Account);

$res = dbi_execute(
    'SELECT chk_statement_id, chk_description, chk_start_date, chk_end_date FROM chk_bank_statement WHERE chk_acct_id = ? ORDER BY chk_end_date DESC',
    [$acct]
);
if (!$res)
    fatalError('Database error: ' . dbi_error());

// Buffer the rows so we can show an empty state when there is nothing yet.
$importUrl = 'import.php?acct=' . $acct;
$items = '';
$count = 0;
while ($row = dbi_fetch_row($res)) {
    $count++;
    $stmtId = (int)$row[0];
    $dateRange = date_to_str((string)$row[2], '__mm__/__dd__/__yyyy__', false) . ' - ' .
        date_to_str((string)$row[3], '__mm__/__dd__/__yyyy__', false);

    $res2 = dbi_execute(
        'SELECT COUNT(*) FROM chk_bank_trans WHERE chk_acct_id = ? AND chk_statement_id = ? AND chk_trans_id IS NULL',
        [$acct, $stmtId]
    );
    $num_not_rec = 0;
    if ($res2 && ($row2 = dbi_fetch_row($res2))) {
        $num_not_rec = (int)$row2[0];
    }
    dbi_free_result($res2);

    $res2 = dbi_execute(
        'SELECT COUNT(*) FROM chk_bank_trans WHERE chk_acct_id = ? AND chk_statement_id = ?',
        [$acct, $stmtId]
    );
    $num_rec = 0;
    if ($res2 && ($row2 = dbi_fetch_row($res2))) {
        $num_rec = (int)$row2[0];
    }
    dbi_free_result($res2);

    $isComplete = ($num_not_rec == 0);
    $itemClass = $isComplete ? 'list-group-item' : 'list-group-item list-group-item-warning';

    $items .= '<div class="' . $itemClass . ' d-flex justify-content-between align-items-center">';
    $items .= '<div>';
    $items .= '<a class="fw-bold text-decoration-none" href="reconcile.php?acct=' . $acct . '&statement=' . $stmtId . '">' .
        htmlentities((string)$row[1]) . '</a>';
    $items .= '<br><small class="text-muted">' . $dateRange . '</small>';
    $items .= '</div>';
    $items .= '<div class="text-end">';
    if ($isComplete) {
        $items .= '<span class="badge bg-success rounded-pill">All ' . $num_rec . ' reconciled</span>';
    } else {
        $items .= '<span class="badge bg-warning text-dark rounded-pill">' . $num_not_rec . ' of ' . $num_rec . ' unreconciled</span>';
        if ($num_not_rec == $num_rec) {
            $items .= ' <a class="btn btn-sm btn-outline-danger ms-2" onclick="return confirm(\'Are you sure you want to delete this statement?\');" href="statement_del.php?acct=' . $acct . '&statement=' . $stmtId . '">Delete</a>';
        }
    }
    $items .= '</div>';
    $items .= '</div>' . "\n";
}
dbi_free_result($res);

if ($count === 0) {
    // Nothing to list: explain where statements come from instead of rendering
    // an empty box.
    echo '<div class="text-center border rounded p-5 mb-3">' . "\n";
    echo '<i class="bi bi-file-earmark-text text-muted" style="font-size: 3rem;" aria-hidden="true"></i>' . "\n";
    echo '<h5 class="mt-3">' . translate('No statements yet') . "</h5>\n";
    echo '<p class="text-muted">' . translate('Statements are created when you import a bank CSV file.') . "</p>\n";
    echo '<a class="btn btn-primary" href="' . htmlspecialchars($importUrl) . '"><i class="bi bi-upload me-1" aria-hidden="true"></i>' .
        translate('Import Statement') . "</a>\n";
    echo "</div>\n";
} else {
    echo '<p><a class="btn btn-sm btn-primary" href="' . htmlspecialchars($importUrl) . '"><i class="bi bi-upload me-1" aria-hidden="true"></i>' .
        translate('Import Statement') . "</a></p>\n";
    echo '<div class="list-group mb-3">' . "\n";
    echo $items;
    echo "</div>\n";
}

print_trailer();
