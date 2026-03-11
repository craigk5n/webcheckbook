<?php
declare(strict_types=1);

function print_header(string $title = 'WebCheckbook'): void
{
    global $acct;
    $acctParam = !empty($acct) ? (int)$acct : 0;
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title><?php echo htmlentities($title); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"
          integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YcnS/1X2Q2q2qU9ljT2cEHP3cTm7fM3J1sN" crossorigin="anonymous" />
    <link rel="stylesheet" type="text/css" href="style.css" />
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark mb-3">
    <div class="container-fluid">
        <a class="navbar-brand" href="accounts.php">Checkbook</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav">
                <li class="nav-item"><a class="nav-link" href="accounts.php">Accounts</a></li>
                <?php if ($acctParam > 0): ?>
                <li class="nav-item"><a class="nav-link" href="list.php?acct=<?php echo $acctParam; ?>">Transactions</a></li>
                <li class="nav-item"><a class="nav-link" href="add_trans.php?acct=<?php echo $acctParam; ?>">Add</a></li>
                <li class="nav-item"><a class="nav-link" href="search.php?acct=<?php echo $acctParam; ?>">Search</a></li>
                <li class="nav-item"><a class="nav-link" href="import.php?acct=<?php echo $acctParam; ?>">Import</a></li>
                <li class="nav-item"><a class="nav-link" href="statements.php?acct=<?php echo $acctParam; ?>">Statements</a></li>
                <li class="nav-item"><a class="nav-link" href="export.php?acct=<?php echo $acctParam; ?>">Export</a></li>
                <li class="nav-item"><a class="nav-link" href="report.php?acct=<?php echo $acctParam; ?>">Reports</a></li>
                <?php endif; ?>
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
        <strong>Go to month:</strong>
        <?php
        $thismonth = (int)date('m');
        $thisyear = (int)date('y');
        for ($i = 0; $i < 18; $i++) {
            $t1 = mktime(3, 0, 0, $thismonth, 1, $thisyear);
            $startT = date('m/d/Y', $t1);
            $t2 = mktime(3, 0, 0, $thismonth + 1, -1, $thisyear);
            $endT = date('m/d/Y', $t2);
            $thismonth--;
            echo '<a class="btn btn-sm btn-outline-secondary me-1 mb-1" href="search.php?acct=' . $acctParam . '&search=&start=' . $startT . '&end=' . $endT . '">' .
                date('M-Y', $t1) . '</a> ';
            if ($thismonth < 1) {
                $thismonth = 12;
                $thisyear--;
            }
        }
        ?>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
</body>
</html>
    <?php
}

function print_heading(string $heading): void
{
    echo '<h2 class="mb-3">' . htmlentities($heading) . "</h2>\n";
}

function print_account_info(array $account): void
{
    echo '<div class="card mb-3"><div class="card-body p-2">';
    echo '<div class="row g-2 small">';
    echo '<div class="col-auto"><strong>Bank:</strong> ' . htmlentities($account['bank']) . '</div>';
    echo '<div class="col-auto"><strong>Name:</strong> ' . htmlentities($account['name']) . '</div>';
    echo '<div class="col-auto"><strong>Acct#:</strong> ' . htmlentities($account['account_no']) . '</div>';
    echo '<div class="col-auto"><strong>Balance:</strong> ' . sprintf('%.2f', $account['balance']) . '</div>';
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
    echo '<div class="table-responsive"><table class="table table-sm table-hover mb-3"><thead class="table-dark"><tr>';
    for ($i = 0; $i < count($header); $i++) {
        echo '<th>' . htmlentities($header[$i]) . '</th>';
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
