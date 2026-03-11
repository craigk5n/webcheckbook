<?php
declare(strict_types=1);

include_once 'includes/config.php';
include_once 'includes/php-dbi.php';
include_once 'includes/functions.php';
include_once 'includes/connect.php';
include_once 'includes/ui.php';
include_once 'includes/translate.php';

$acct = getIntValue('acct');
if (empty($acct)) {
    fatalError('No account specified');
}

$Account = get_account_info($acct);

print_header(translate('Account') . ': ' . $Account['name']);
print_heading(translate('Search') . ' ' . translate('Account') . ': ' . $Account['name']);
print_account_info($Account);

$start = getValue('start');
$end = getValue('end');
$search = getValue('search');
$rec = getValue('reconciled');
$startPretty = '';
$endPretty = '';

if (!empty($start)) {
    $startPretty = $start;
    $start = parse_date_input($start);
}

if (!empty($end)) {
    $endPretty = $end;
    $end = parse_date_input($end);
}

?>
<form action="search.php">
<input type="hidden" name="acct" value="<?php echo $acct; ?>" />
<table border="0">
<tr><td><b>Description:</b></td><td><input name="search" value="<?php echo htmlentities($search);?>" /></td></tr>
<tr><td><b>Date Range:</b></td><td>
  <input name="start" size="11" value="<?php echo htmlentities($startPretty);?>" />
  <input name="end" size="11" value="<?php echo htmlentities($endPretty);?>" />
</td></tr>
<tr><td><b>Reconciled:</b></td>
<td>
  <input type="radio" id="NotReconciled" name="reconciled" value="no"
   <?php if (!empty($rec) && $rec == 'no') echo 'checked="checked"';?> >
    <label for="NotReconciled">No</label>
  <input type="radio" id="Reconciled" name="reconciled" value="yes"
   <?php if (!empty($rec) && $rec == 'yes') echo 'checked="checked"';?> >
    <label for="Reconciled">Yes</label>
  <input type="radio" id="Either" name="reconciled" value="either"
   <?php if (!empty($rec) && $rec == 'either') echo 'checked="checked"';?> >
    <label for="Either">Either</label>
</td></tr>
<tr><td colspan="2"><input type="submit" value="Search" /></td></tr>
</table>
</form>
<?php

if (!empty($search) || !empty($start) || !empty($end)) {
    $sql = 'SELECT chk_trans_id, chk_type, chk_no, chk_amount, chk_date, chk_description, chk_reconciled ' .
           'FROM chk_trans WHERE chk_acct_id = ?';
    $params = [$acct];

    if (!empty($search)) {
        $sql .= ' AND chk_description LIKE ?';
        $params[] = '%' . $search . '%';
    }
    if (!empty($start)) {
        $sql .= ' AND chk_date >= ?';
        $params[] = $start;
    }
    if (!empty($end)) {
        $sql .= ' AND chk_date <= ?';
        $params[] = $end;
    }
    if (!empty($rec) && $rec == 'yes') {
        $sql .= " AND chk_reconciled = 'Y'";
    }
    if (!empty($rec) && $rec == 'no') {
        $sql .= " AND NOT chk_reconciled = 'Y'";
    }
    $sql .= ' ORDER BY chk_date, chk_type, chk_no ASC';

    $res = dbi_execute($sql, $params);
    open_table(['Date', 'Chk#', 'Comment', 'Amount', 'Total', ' ']);
    $bal = 0.0;
    if ($res) {
        $out = [];
        $cnt = 0;
        $lastDate = '';
        while ($row = dbi_fetch_row($res)) {
            $cnt++;
            if ($cnt == 0 || $row[4] != $lastDate) {
                $out[] = "<tr><td colspan=\"6\" style=\"height: 1px; background-color: #000;\"></td></tr>\n";
            }
            if ($row[3] > 0) {
                $class = 'deposit';
            } else {
                $class = ($cnt % 2 == 0) ? 'withdrawal-even' : 'withdrawal-odd';
            }
            $line = "<tr><td class=\"$class\">" .
                "<a href=\"edit_trans.php?acct=$acct&trans=" . (int)$row[0] . "\">" .
                date_to_str($row[4], '__mm__/__dd__/__yyyy__', false) .
                "</a></td>";
            $num = empty($row[2]) ? '-' : htmlentities((string)$row[2]);
            $line .= "<td class=\"$class\">" . $num . "</td>";
            $line .= "<td class=\"$class\">" . htmlentities($row[5]) . "</td>";
            $line .= "<td class=\"$class\" align=\"right\">" . sprintf('%.02f', $row[3]) . "</td>";
            $bal += $row[3];
            $line .= "<td class=\"$class\" align=\"right\">" . sprintf('%.02f', $bal) . "</td>";
            $line .= "<td class=\"$class\" align=\"right\"><img src=\"" .
                ($row[6] == 'Y' ? 'images/reconciled.png' : 'images/not_reconciled.png') .
                "\" alt=\"rec\" /></td>";
            $out[] = $line;
            $lastDate = $row[4];
        }
        dbi_free_result($res);
    } else {
        fatalError(translate('Database error') . ': ' . dbi_error());
    }

    $out[] = "<tr><td colspan=\"6\" style=\"height: 1px; background-color: #000;\"></td></tr>\n";

    for ($i = 0; $i < count($out) && $i < 500; $i++) {
        print $out[$i];
    }
    close_table();
}

print_trailer();
