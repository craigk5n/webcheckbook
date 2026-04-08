<?php
declare(strict_types=1);

function print_header(string $title = 'WebCheckbook'): void
{
    global $acct;
    $acctParam = !empty($acct) ? (int)$acct : 0;
    $currentPage = basename($_SERVER['PHP_SELF'] ?? '');
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title><?php echo htmlentities($title); ?></title>
    <link rel="stylesheet" href="assets/bootstrap/css/bootstrap.min.css" />
    <link rel="stylesheet" href="assets/bootstrap-icons/font/bootstrap-icons.min.css" />
    <link rel="stylesheet" type="text/css" href="style.css" />
    <?php
    $calStyle = $_SERVER['DOCUMENT_ROOT'] . '/calstyle.css';
    if (is_file($calStyle)) { include $calStyle; }
    ?>
</head>
<body class="bg-light">
<?php
$calHeader = $_SERVER['DOCUMENT_ROOT'] . '/calheader.php';
if (is_file($calHeader)) { include $calHeader; }
?>
<nav class="navbar navbar-expand-sm navbar-dark bg-dark shadow-sm mb-3 sticky-top py-1">
    <div class="container-fluid">
        <a class="navbar-brand fw-bold py-1" href="accounts.php"><i class="bi bi-journal-check me-1"></i>Checkbook</a>
        <button class="navbar-toggler py-1" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto">
                <?php
                // Primary nav: core per-account actions
                $primary = [['accounts.php', 'Accounts', 'bi-bank']];
                if ($acctParam > 0) {
                    $primary[] = ['list.php?acct=' . $acctParam, 'Transactions', 'bi-list-ul'];
                    $primary[] = ['add_trans.php?acct=' . $acctParam, 'Add', 'bi-plus-circle'];
                    $primary[] = ['search.php?acct=' . $acctParam, 'Search', 'bi-search'];
                }
                foreach ($primary as [$href, $label, $icon]) {
                    $pageFile = strtok($href, '?');
                    $active = ($currentPage === $pageFile) ? ' active' : '';
                    echo '<li class="nav-item"><a class="nav-link py-1' . $active . '" href="' . htmlspecialchars($href) . '" title="' . htmlspecialchars($label) . '">'
                        . '<i class="' . $icon . ' me-1"></i><span class="d-sm-none d-md-inline">' . htmlspecialchars($label) . '</span></a></li>' . "\n";
                }

                // Tools dropdown: less-frequent actions
                if ($acctParam > 0) {
                    $tools = [
                        ['import.php?acct=' . $acctParam, 'Import', 'bi-upload'],
                        ['statements.php?acct=' . $acctParam, 'Statements', 'bi-file-earmark-text'],
                        ['export.php?acct=' . $acctParam, 'Export', 'bi-download'],
                        ['report.php?acct=' . $acctParam, 'Reports', 'bi-bar-chart'],
                    ];
                    $toolActive = false;
                    foreach ($tools as [$h, , ]) {
                        if ($currentPage === strtok($h, '?')) { $toolActive = true; break; }
                    }
                    echo '<li class="nav-item dropdown"><a class="nav-link dropdown-toggle py-1' . ($toolActive ? ' active' : '') . '" href="#" data-bs-toggle="dropdown"><i class="bi bi-tools me-1"></i><span class="d-sm-none d-md-inline">Tools</span></a><ul class="dropdown-menu dropdown-menu-end">';
                    foreach ($tools as [$href, $label, $icon]) {
                        $active = ($currentPage === strtok($href, '?')) ? ' active' : '';
                        echo '<li><a class="dropdown-item' . $active . '" href="' . htmlspecialchars($href) . '"><i class="' . $icon . ' me-2"></i>' . htmlspecialchars($label) . '</a></li>';
                    }
                    echo '</ul></li>';
                }
                ?>
            </ul>
        </div>
    </div>
</nav>
<div class="container-fluid px-4">
    <?php
}

function print_trailer(): void
{
    global $acct;
    $acctParam = !empty($acct) ? (int)$acct : 0;
    ?>
    <hr />
    <div class="mb-3">
        <div class="d-flex flex-wrap align-items-center gap-1">
            <strong class="me-2">Go to month:</strong>
            <?php
            $thismonth = (int)date('m');
            $thisyear = (int)date('y');
            for ($i = 0; $i < 18; $i++) {
                $t1 = mktime(3, 0, 0, $thismonth, 1, $thisyear);
                $startT = date('m/d/Y', $t1);
                $t2 = mktime(3, 0, 0, $thismonth + 1, -1, $thisyear);
                $endT = date('m/d/Y', $t2);
                $thismonth--;
                $btnClass = ($i === 0) ? 'btn btn-sm btn-outline-primary' : 'btn btn-sm btn-outline-secondary';
                echo '<a class="' . $btnClass . '" href="search.php?acct=' . $acctParam . '&search=&start=' . $startT . '&end=' . $endT . '">' .
                    date('M-Y', $t1) . '</a>';
                if ($thismonth < 1) {
                    $thismonth = 12;
                    $thisyear--;
                }
            }
            ?>
        </div>
    </div>
</div>
<?php
$calTrailer = $_SERVER['DOCUMENT_ROOT'] . '/caltrailer.php';
if (is_file($calTrailer)) { include $calTrailer; }
?>
<script src="assets/bootstrap/js/bootstrap.bundle.min.js"></script>
</body>
</html>
    <?php
}

function print_heading(string $heading): void
{
    echo '<h2 class="mb-3 fw-semibold">' . htmlentities($heading) . "</h2>\n";
}

function print_account_info(array $account): void
{
    $balance = $account['balance'] ?? 0;
    $borderClass = $balance >= 0 ? 'border-start border-success border-3' : 'border-start border-danger border-3';
    echo '<div class="card mb-3 ' . $borderClass . '"><div class="card-body p-2">';
    echo '<div class="row g-2 small">';
    echo '<div class="col-auto"><strong>Bank:</strong> ' . htmlentities($account['bank']) . '</div>';
    echo '<div class="col-auto"><strong>Name:</strong> ' . htmlentities($account['name']) . '</div>';
    echo '<div class="col-auto"><strong>Acct#:</strong> ' . htmlentities($account['account_no']) . '</div>';
    $balClass = $balance >= 0 ? 'text-success' : 'text-danger';
    echo '<div class="col-auto"><strong>Balance:</strong> <span class="' . $balClass . ' fw-bold">' . sprintf('%.2f', $balance) . '</span></div>';
    echo '<div class="col-auto"><strong>Bank Bal:</strong> ' . sprintf('%.2f', $account['bank_balance']) . '</div>';
    if (!empty($account['start_date'])) {
        echo '<div class="col-auto"><strong>Begin:</strong> ' . date_to_str($account['start_date'], '__mm__/__dd__/__yyyy__', false) . '</div>';
    }
    if (!empty($account['end_date'])) {
        echo '<div class="col-auto"><strong>End:</strong> ' . date_to_str($account['end_date'], '__mm__/__dd__/__yyyy__', false) . '</div>';
    }
    echo '</div></div></div>';
}

function open_table(array $header): void
{
    echo '<div class="table-responsive"><table class="table table-sm table-hover table-striped mb-3"><thead class="table-dark"><tr>';
    for ($i = 0; $i < count($header); $i++) {
        echo '<th class="text-nowrap">' . htmlentities($header[$i]) . '</th>';
    }
    echo "</tr></thead><tbody>\n";
}

function print_table_cell(string $cell, bool $escape = true, bool $inRed = false): void
{
    if ($inRed) {
        echo '<td class="text-danger">';
    } else {
        echo '<td>';
    }
    echo $escape ? htmlentities($cell) : $cell;
    echo '</td>';
}

function close_table(): void
{
    echo "</tbody></table></div>\n";
}

function print_transaction(
    array $trans,
    bool $include_header = true,
    bool $include_trailer = true,
    string $radio_name = '',
    $radio_value = '',
    bool $defaultSelected = false,
    bool $enabled = true,
    bool $wrongAmount = false
): void {
    if ($include_header) {
        if (empty($radio_name)) {
            $arr = [translate('Date'), translate('Chk#'), translate('Amount'), translate('Description'), ' '];
        } else {
            $arr = [' ', translate('Date'), translate('Chk#'), translate('Amount'), translate('Description'), ' '];
        }
        open_table($arr);
    }
    $rowClass = ($trans['amount'] ?? 0) > 0 ? 'table-success' : '';
    echo "<tr class=\"$rowClass\">";
    if (!empty($radio_name)) {
        $sel = $defaultSelected ? 'checked' : '';
        $enable = $enabled ? '' : 'disabled';
        echo "<td><input class=\"form-check-input\" name=\"$radio_name\" type=\"radio\" value=\"$radio_value\" $sel $enable></td>";
    }
    $url = 'edit_trans.php?acct=' . ($trans['account'] ?? '') . '&trans=' . ($trans['trans_id'] ?? '');
    print_table_cell('<a href="' . $url . '">' .
        date_to_str($trans['date'], '__mm__/__dd__/__yyyy__', false) . '</a>', false);
    print_table_cell((empty($trans['no']) || $trans['no'] == 0) ? '-' : (string)$trans['no']);
    print_table_cell(sprintf('%.2f', $trans['amount']), true, $wrongAmount);
    print_table_cell($trans['description'] ?? '');
    $reconciled = $trans['reconciled'] ?? 'N';
    $badge = $reconciled === 'Y'
        ? '<span class="badge bg-success">R</span>'
        : '<span class="badge bg-secondary">-</span>';
    print_table_cell($badge, false);
    echo "</tr>\n";
    if ($include_trailer) {
        close_table();
    }
}

function print_bank_transaction(array $trans, bool $include_header = true, bool $include_trailer = true): void
{
    if ($include_header) {
        open_table([translate('Date'), translate('Chk#'), translate('Amount'), translate('Description'), translate('Memo'), ' ']);
    }
    $rowClass = ($trans['amount'] ?? 0) > 0 ? 'table-success' : '';
    echo "<tr class=\"$rowClass\">";
    print_table_cell(date_to_str($trans['date'], '__mm__/__dd__/__yyyy__', false));
    print_table_cell(empty($trans['no']) ? '-' : (string)$trans['no']);
    print_table_cell(sprintf('%.2f', $trans['amount']));
    print_table_cell($trans['description'] ?? '');
    print_table_cell($trans['memo'] ?? '');
    $badge = !empty($trans['trans_id'])
        ? '<span class="badge bg-success">R</span>'
        : '<span class="badge bg-secondary">-</span>';
    print_table_cell($badge, false);
    echo "</tr>\n";
    if ($include_trailer) {
        close_table();
    }
}
