<?php
declare(strict_types=1);

/**
 * Exports transactions for a specified account in the checkbook application.
 *
 * @package Checkbook
 */

include_once 'includes/config.php';
include_once 'includes/php-dbi.php';
include_once 'includes/functions.php';
include_once 'includes/connect.php';
include_once 'includes/ui.php';
include_once 'includes/translate.php';

$acct = getIntValue('acct', true);
if (empty($acct)) {
    fatalError(translate('No account specified'));
}

$Account = get_account_info($acct);

// Process form inputs
$start = getValue('start');
$end = getValue('end');
$reconciled = getValue('reconciled');
$format = getValue('format');
$start_str = '';
$end_str = '';
$start_pretty = '';
$end_pretty = '';

if (!empty($start)) {
    if (!preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{2,4})$/', $start, $dateAr)) {
        fatalError(translate('Invalid start date format'));
    }
    $month = (int)$dateAr[1];
    $day = (int)$dateAr[2];
    $year = (int)$dateAr[3];
    if ($year < 100) {
        $year += 2000;
    } elseif ($year > 2050) {
        $year -= 100;
    }
    if (!checkdate($month, $day, $year)) {
        fatalError(translate('Invalid start date'));
    }
    $start_str = sprintf('%04d%02d%02d', $year, $month, $day);
    $start_pretty = sprintf('%d/%d/%d', $month, $day, $year);
    if (empty($end)) {
        $end_str = sprintf('%04d%02d%02d', $year + 1, $month, $day);
        $end_pretty = sprintf('%d/%d/%d', $month, $day, $year + 1);
    }
}

if (!empty($end)) {
    if (!preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{2,4})$/', $end, $dateAr)) {
        fatalError(translate('Invalid end date format'));
    }
    $month = (int)$dateAr[1];
    $day = (int)$dateAr[2];
    $year = (int)$dateAr[3];
    if ($year < 100) {
        $year += 2000;
    } elseif ($year > 2050) {
        $year -= 100;
    }
    if (!checkdate($month, $day, $year)) {
        fatalError(translate('Invalid end date'));
    }
    $end_str = sprintf('%04d%02d%02d', $year, $month, $day);
    $end_pretty = sprintf('%d/%d/%d', $month, $day, $year);
}

$do_export = !empty($start_str) && !empty($format);
if (!in_array($format, ['html', 'text'], true)) {
    $format = 'text';
}
if ($do_export && $format === 'text') {
    header('Content-Type: text/plain');
}

if ($do_export) {
    $sql = 'SELECT chk_trans_id, chk_type, chk_no, chk_amount, chk_date, chk_description, chk_reconciled ' .
           'FROM chk_trans WHERE chk_acct_id = ?';
    $params = [$acct];
    if (!empty($start_str)) {
        $sql .= ' AND chk_date >= ?';
        $params[] = $start_str;
    }
    if (!empty($end_str)) {
        $sql .= ' AND chk_date <= ?';
        $params[] = $end_str;
    }
    if ($reconciled === 'yes') {
        $sql .= " AND chk_reconciled = 'Y'";
    } elseif ($reconciled === 'no') {
        $sql .= " AND chk_reconciled = 'N'";
    }
    $sql .= ' ORDER BY chk_date, chk_type, chk_no ASC';
    $res = dbi_execute($sql, $params);

    $rows = [];
    $totals = [];
    $last_date = '';
    if ($res) {
        if ($format === 'html') {
            $title = translate('Account') . ': ' . htmlspecialchars($Account['name']);
            print_header($title);
            print_heading(translate('Export') . ' ' . translate('Account') . ': ' . htmlspecialchars($Account['name']));
            print_account_info($Account);
            open_table(['Date', 'Chk#', 'Comment', 'Amount']);
        } else {
            echo 'Account: ' . htmlspecialchars($Account['name']) . "\n\n";
            printf("%10s %6s %50s %12s\n", 'Date', 'Check#', 'Description', 'Amount');
            printf("%10s %6s %50s %12s\n", '----------', '------', '--------------------------------------------------', '------------');
        }

        while ($row = dbi_fetch_row($res)) {
            $rowClass = $row[3] > 0 ? 'table-success' : '';
            if (empty($rows) || $row[4] !== $last_date) {
                $rows[] = $format === 'html'
                    ? '<tr><td colspan="4" style="height: 1px; background-color: #000;"></td></tr>'
                    : "----------\n";
            }
            $num = $row[2] !== null ? (string)$row[2] : '-';
            $description = strtoupper((string)$row[5]);
            if ($format === 'html') {
                $rows[] = sprintf(
                    '<tr class="%s">' .
                    '<td><a href="edit_trans.php?acct=%d&trans=%d">%s</a></td>' .
                    '<td>%s</td>' .
                    '<td>%s</td>' .
                    '<td class="text-end">%.2f</td>' .
                    '</tr>',
                    $rowClass, $acct, (int)$row[0], date_to_str((string)$row[4], '__mm__/__dd__/__yyyy__', false),
                    htmlspecialchars($num),
                    htmlspecialchars($description),
                    (float)$row[3]
                );
            } else {
                $rows[] = sprintf(
                    "%10s %6s %50s %12.2f\n",
                    date_to_str((string)$row[4], '__mm__/__dd__/__yyyy__', false),
                    $num,
                    $description,
                    (float)$row[3]
                );
            }
            $last_date = (string)$row[4];
            if (!isset($totals[$description])) {
                $totals[$description] = 0;
            }
            $totals[$description] += (float)$row[3];
        }
        dbi_free_result($res);

        if ($format === 'html') {
            $rows[] = '<tr><td colspan="4" style="height: 1px; background-color: #000;"></td></tr>';
        } else {
            $rows[] = "----------\n";
        }

        foreach ($rows as $row) {
            echo $row;
        }

        if ($format === 'html') {
            close_table();
            echo '<h3>' . translate('Deposit Totals') . "</h3>\n";
            open_table([translate('Amount'), translate('Description')]);
        } else {
            echo "\n" . translate('Deposit Totals') . "\n";
            printf("%10s %40s\n", '----------', '----------------------------------------');
        }

        $sort_keys = [];
        foreach ($totals as $desc => $total) {
            $sort_keys[] = sprintf('%12.2f %s', $total > 0 ? $total : -$total, $desc);
        }
        arsort($sort_keys);

        foreach ($sort_keys as $key) {
            $desc = substr($key, 13);
            if ($totals[$desc] > 0) {
                if ($format === 'html') {
                    echo '<tr><td class="text-end">' . sprintf('%.2f', $totals[$desc]) . '</td><td>' . htmlspecialchars($desc) . "</td></tr>\n";
                } else {
                    printf("%10.2f %40s\n", $totals[$desc], $desc);
                }
            }
        }

        if ($format === 'html') {
            close_table();
            echo '<h3>' . translate('Expense Totals') . "</h3>\n";
            open_table([translate('Amount'), translate('Description')]);
        } else {
            echo "\n" . translate('Expense Totals') . "\n";
            printf("%10s %40s\n", '----------', '----------------------------------------');
        }

        foreach ($sort_keys as $key) {
            $desc = substr($key, 13);
            if ($totals[$desc] < 0) {
                if ($format === 'html') {
                    echo '<tr><td class="text-end">' . sprintf('%.2f', $totals[$desc]) . '</td><td>' . htmlspecialchars($desc) . "</td></tr>\n";
                } else {
                    printf("%10.2f %40s\n", $totals[$desc], $desc);
                }
            }
        }

        if ($format === 'html') {
            close_table();
        }
    } else {
        fatalError(translate('Database error') . ': Unable to retrieve transactions.');
    }
} else {
    $title = translate('Account') . ': ' . htmlspecialchars($Account['name']);
    print_header($title);
    print_heading(translate('Export') . ' ' . translate('Account') . ': ' . htmlspecialchars($Account['name']));
    print_account_info($Account);
?>

<form action="export.php" method="GET">
    <input type="hidden" name="acct" value="<?php echo htmlspecialchars((string)$Account['acct_id']); ?>" />
    <div class="row mb-3">
        <label class="col-sm-2 col-form-label"><?php echo translate('Date Range'); ?>:</label>
        <div class="col-sm-3">
            <input type="text" class="form-control" name="start" placeholder="Start" value="<?php echo htmlspecialchars($start_pretty ?? ''); ?>" />
        </div>
        <div class="col-sm-3">
            <input type="text" class="form-control" name="end" placeholder="End" value="<?php echo htmlspecialchars($end_pretty ?? ''); ?>" />
        </div>
    </div>
    <div class="row mb-3">
        <label class="col-sm-2 col-form-label"><?php echo translate('Reconciled'); ?>:</label>
        <div class="col-sm-6">
            <div class="form-check form-check-inline">
                <input class="form-check-input" type="radio" id="NotReconciled" name="reconciled" value="no" <?php echo $reconciled === 'no' ? 'checked' : ''; ?> />
                <label class="form-check-label" for="NotReconciled"><?php echo translate('No'); ?></label>
            </div>
            <div class="form-check form-check-inline">
                <input class="form-check-input" type="radio" id="Reconciled" name="reconciled" value="yes" <?php echo $reconciled === 'yes' ? 'checked' : ''; ?> />
                <label class="form-check-label" for="Reconciled"><?php echo translate('Yes'); ?></label>
            </div>
            <div class="form-check form-check-inline">
                <input class="form-check-input" type="radio" id="Either" name="reconciled" value="either" <?php echo $reconciled === 'either' ? 'checked' : ''; ?> />
                <label class="form-check-label" for="Either"><?php echo translate('Either'); ?></label>
            </div>
        </div>
    </div>
    <div class="row mb-3">
        <label for="format" class="col-sm-2 col-form-label"><?php echo translate('Format'); ?>:</label>
        <div class="col-sm-3">
            <select class="form-select" id="format" name="format">
                <option value="text" <?php echo $format === 'text' ? 'selected' : ''; ?>><?php echo translate('Text'); ?></option>
                <option value="html" <?php echo $format === 'html' ? 'selected' : ''; ?>><?php echo translate('HTML'); ?></option>
            </select>
        </div>
    </div>
    <div class="row">
        <div class="col-sm-6 offset-sm-2">
            <button type="submit" class="btn btn-primary"><?php echo translate('Export'); ?></button>
        </div>
    </div>
</form>

<?php
}

if ($format === 'html' || !$do_export) {
    print_trailer();
}
?>
