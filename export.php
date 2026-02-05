<?php
declare(strict_types=1);

/**
 * Exports transactions for a specified account in the checkbook application.
 *
 * Displays a form to select date range, reconciled status, and format (HTML or text),
 * and generates the export output based on user input. Updated for PHP 8 best practices
 * by Grok (xAI) in September 2025.
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

// Get account info using prepared statement
$sql = 'SELECT chk_bank, chk_name, chk_account_no, chk_balance, chk_bank_balance ' .
       'FROM chk_account WHERE chk_acct_id = ?';
$res = dbi_execute($sql, [$acct]);
$account = [];
if ($res) {
    if ($row = dbi_fetch_row($res)) {
        $account = [
            'acct_id' => $acct,
            'bank' => (string)$row[0],
            'name' => (string)$row[1],
            'account_no' => (string)$row[2],
            'balance' => (float)$row[3],
            'bank_balance' => (float)$row[4],
        ];
        dbi_free_result($res);
    } else {
        fatalError(translate('No such account: ') . $acct);
    }
} else {
    fatalError(translate('Database error') . ': Unable to retrieve account information.');
}

// Get first and last transaction date
$sql = 'SELECT MIN(chk_date), MAX(chk_date) FROM chk_trans WHERE chk_acct_id = ?';
$res = dbi_execute($sql, [$acct]);
if ($res) {
    if ($row = dbi_fetch_row($res)) {
        $account['start_date'] = (string)($row[0] ?? '');
        $account['end_date'] = (string)($row[1] ?? '');
    }
    dbi_free_result($res);
}

// Process form inputs
$start = getValue('start');
$end = getValue('end');
$reconciled = getValue('reconciled');
$format = getValue('format');
$start_str = '';
$end_str = '';

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
            $title = translate('Account') . ': ' . htmlspecialchars($account['name']);
            print_header($title);
            print_heading(translate('Export') . ' ' . translate('Account') . ': ' . htmlspecialchars($account['name']));
            print_account_info($account);
            open_table(['Date', 'Chk#', 'Comment', 'Amount']);
        } else {
            echo 'Account: ' . htmlspecialchars($account['name']) . "\n\n";
            printf("%10s %6s %50s %12s\n", 'Date', 'Check#', 'Description', 'Amount');
            printf("%10s %6s %50s %12s\n", '----------', '------', '--------------------------------------------------', '------------');
        }

        while ($row = dbi_fetch_row($res)) {
            $class = $row[3] > 0 ? 'deposit' : (count($rows) % 2 === 0 ? 'withdrawal-even' : 'withdrawal-odd');
            if (empty($rows) || $row[4] !== $last_date) {
                $rows[] = $format === 'html'
                    ? '<tr><td colspan="4" style="height: 1px; background-color: #000;"></td></tr>'
                    : "----------\n";
            }
            $num = $row[2] !== null ? (string)$row[2] : '-';
            $description = strtoupper((string)$row[5]);
            if ($format === 'html') {
                $rows[] = sprintf(
                    '<tr>' .
                    '<td class="%s"><a href="edit_trans.php?acct=%d&trans=%d">%s</a></td>' .
                    '<td class="%s">%s</td>' .
                    '<td class="%s">%s</td>' .
                    '<td class="%s" align="right">%.2f</td>' .
                    '</tr>',
                    $class, $acct, (int)$row[0], date_to_str((string)$row[4], '__mm__/__dd__/__yyyy__', false),
                    $class, htmlspecialchars($num),
                    $class, htmlspecialchars($description),
                    $class, (float)$row[3]
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
            echo '<table><tr><th>' . translate('Amount') . '</th><th>' . translate('Description') . "</th></tr>\n";
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
                    echo sprintf('<tr><td>%10.2f</td><td>%s</td></tr>', $totals[$desc], htmlspecialchars($desc)) . "\n";
                } else {
                    printf("%10.2f %40s\n", $totals[$desc], $desc);
                }
            }
        }

        if ($format === 'html') {
            echo "</table>\n";
            echo '<h3>' . translate('Expense Totals') . "</h3>\n";
            echo '<table><tr><th>' . translate('Amount') . '</th><th>' . translate('Description') . "</th></tr>\n";
        } else {
            echo "\n" . translate('Expense Totals') . "\n";
            printf("%10s %40s\n", '----------', '----------------------------------------');
        }

        foreach ($sort_keys as $key) {
            $desc = substr($key, 13);
            if ($totals[$desc] < 0) {
                if ($format === 'html') {
                    echo sprintf('<tr><td>%10.2f</td><td>%s</td></tr>', $totals[$desc], htmlspecialchars($desc)) . "\n";
                } else {
                    printf("%10.2f %40s\n", $totals[$desc], $desc);
                }
            }
        }

        if ($format === 'html') {
            echo "</table>\n";
        }
    } else {
        fatalError(translate('Database error') . ': Unable to retrieve transactions.');
    }
} else {
    $title = translate('Account') . ': ' . htmlspecialchars($account['name']);
    print_header($title);
    print_heading(translate('Export') . ' ' . translate('Account') . ': ' . htmlspecialchars($account['name']));
    print_account_info($account);
?>

<form action="export.php" method="GET">
    <input type="hidden" name="acct" value="<?php echo htmlspecialchars((string)$account['acct_id']); ?>" />
    <table border="0">
        <tr>
            <td><b><?php echo translate('Date Range'); ?>:</b></td>
            <td>
                <input name="start" size="11" value="<?php echo htmlspecialchars($start_pretty ?? ''); ?>" />
                <input name="end" size="11" value="<?php echo htmlspecialchars($end_pretty ?? ''); ?>" />
            </td>
        </tr>
        <tr>
            <td><b><?php echo translate('Reconciled'); ?>:</b></td>
            <td>
                <input type="radio" id="NotReconciled" name="reconciled" value="no" <?php echo $reconciled === 'no' ? 'checked' : ''; ?> />
                <label for="NotReconciled"><?php echo translate('No'); ?></label>
                <input type="radio" id="Reconciled" name="reconciled" value="yes" <?php echo $reconciled === 'yes' ? 'checked' : ''; ?> />
                <label for="Reconciled"><?php echo translate('Yes'); ?></label>
                <input type="radio" id="Either" name="reconciled" value="either" <?php echo $reconciled === 'either' ? 'checked' : ''; ?> />
                <label for="Either"><?php echo translate('Either'); ?></label>
            </td>
        </tr>
        <tr>
            <td><b><?php echo translate('Format'); ?>:</b></td>
            <td>
                <select name="format">
                    <option value="text" <?php echo $format === 'text' ? 'selected' : ''; ?>><?php echo translate('Text'); ?></option>
                    <option value="html" <?php echo $format === 'html' ? 'selected' : ''; ?>><?php echo translate('HTML'); ?></option>
                </select>
            </td>
        </tr>
        <tr>
            <td colspan="2"><input type="submit" value="<?php echo translate('Export'); ?>" /></td>
        </tr>
    </table>
</form>

<?php
}

if ($format === 'html' || !$do_export) {
    print_trailer();
}
?>