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

// Amount filters match the absolute value, so one range covers both deposits
// and withdrawals.  chk_amount is DECIMAL, so the bounds compare exactly.
$minAmount = parse_amount_input(getValue('minAmount'));
$maxAmount = parse_amount_input(getValue('maxAmount'));
$minInvalid = getValue('minAmount') !== '' && $minAmount === null;
$maxInvalid = getValue('maxAmount') !== '' && $maxAmount === null;
$amountSwapped = false;
if ($minAmount !== null && $maxAmount !== null && $minAmount > $maxAmount) {
    [$minAmount, $maxAmount] = [$maxAmount, $minAmount];
    $amountSwapped = true;
}
$minPretty = $minAmount !== null ? sprintf('%.2f', $minAmount) : getValue('minAmount');
$maxPretty = $maxAmount !== null ? sprintf('%.2f', $maxAmount) : getValue('maxAmount');

// Optional exact check-number lookup.  chk_no is an INT column, so "0123" and
// "123" are the same check.
$checkNoRaw = trim(getValue('checkNo'));
$checkNoInvalid = $checkNoRaw !== '' && !ctype_digit($checkNoRaw);
$checkNo = ($checkNoRaw !== '' && !$checkNoInvalid) ? normalize_check_number($checkNoRaw) : '';
$checkNoPretty = $checkNo !== '' ? $checkNo : $checkNoRaw;

?>
<form action="search.php">
<input type="hidden" name="acct" value="<?php echo $acct; ?>" />
<div class="row mb-3">
    <label for="search" class="col-sm-2 col-form-label">Description:</label>
    <div class="col-sm-6">
        <input type="text" class="form-control" id="search" name="search" value="<?php echo htmlentities($search); ?>" />
    </div>
</div>
<div class="row mb-3">
    <label for="checkNo" class="col-sm-2 col-form-label">Check #:</label>
    <div class="col-sm-3">
        <input type="text" class="form-control<?php echo $checkNoInvalid ? ' is-invalid' : ''; ?>" id="checkNo" name="checkNo"
            inputmode="numeric" placeholder="Optional" aria-label="Check number" value="<?php echo htmlentities($checkNoPretty); ?>" />
        <?php if ($checkNoInvalid): ?>
            <div class="invalid-feedback d-block">Enter a check number, for example 1234.</div>
        <?php endif; ?>
    </div>
    <div class="col-sm-6 offset-sm-2">
        <div class="form-text">Matches one exact check number. Leading zeros are ignored.</div>
    </div>
</div>
<div class="row mb-3">
    <label class="col-sm-2 col-form-label">Date Range:</label>
    <div class="col-sm-3">
        <input type="text" class="form-control" name="start" placeholder="Start" value="<?php echo htmlentities($startPretty); ?>" />
    </div>
    <div class="col-sm-3">
        <input type="text" class="form-control" name="end" placeholder="End" value="<?php echo htmlentities($endPretty); ?>" />
    </div>
</div>
<div class="row mb-3">
    <label for="minAmount" class="col-sm-2 col-form-label">Amount Range:</label>
    <div class="col-sm-3">
        <div class="input-group">
            <span class="input-group-text">$</span>
            <input type="text" class="form-control<?php echo $minInvalid ? ' is-invalid' : ''; ?>" id="minAmount" name="minAmount"
                inputmode="decimal" placeholder="Min" aria-label="Minimum amount" value="<?php echo htmlentities($minPretty); ?>" />
        </div>
        <?php if ($minInvalid): ?>
            <div class="invalid-feedback d-block">Enter a number, for example 25 or 1,234.56.</div>
        <?php endif; ?>
    </div>
    <div class="col-sm-3">
        <div class="input-group">
            <span class="input-group-text">$</span>
            <input type="text" class="form-control<?php echo $maxInvalid ? ' is-invalid' : ''; ?>" id="maxAmount" name="maxAmount"
                inputmode="decimal" placeholder="Max" aria-label="Maximum amount" value="<?php echo htmlentities($maxPretty); ?>" />
        </div>
        <?php if ($maxInvalid): ?>
            <div class="invalid-feedback d-block">Enter a number, for example 25 or 1,234.56.</div>
        <?php endif; ?>
    </div>
    <div class="col-sm-6 offset-sm-2">
        <div class="form-text">Matches the amount regardless of sign, so a range finds both deposits and withdrawals. Leave either box empty for an open-ended range.</div>
    </div>
</div>
<div class="row mb-3">
    <label class="col-sm-2 col-form-label">Reconciled:</label>
    <div class="col-sm-6">
        <div class="form-check form-check-inline">
            <input class="form-check-input" type="radio" id="NotReconciled" name="reconciled" value="no"
                <?php if (!empty($rec) && $rec == 'no') echo 'checked'; ?> />
            <label class="form-check-label" for="NotReconciled">No</label>
        </div>
        <div class="form-check form-check-inline">
            <input class="form-check-input" type="radio" id="Reconciled" name="reconciled" value="yes"
                <?php if (!empty($rec) && $rec == 'yes') echo 'checked'; ?> />
            <label class="form-check-label" for="Reconciled">Yes</label>
        </div>
        <div class="form-check form-check-inline">
            <input class="form-check-input" type="radio" id="Either" name="reconciled" value="either"
                <?php if (!empty($rec) && $rec == 'either') echo 'checked'; ?> />
            <label class="form-check-label" for="Either">Either</label>
        </div>
    </div>
</div>
<div class="row">
    <div class="col-sm-6 offset-sm-2">
        <button type="submit" class="btn btn-primary">Search</button>
    </div>
</div>
</form>
<?php

if ($amountSwapped) {
    echo '<div class="alert alert-info py-2" role="alert"><i class="bi bi-info-circle me-1" aria-hidden="true"></i>' .
        'Minimum was larger than maximum, so the two amounts were swapped.</div>' . "\n";
}

$hasCriteria = !empty($search) || !empty($start) || !empty($end)
    || $minAmount !== null || $maxAmount !== null || $checkNo !== '';

if ($hasCriteria && !$minInvalid && !$maxInvalid && !$checkNoInvalid) {
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
    if ($checkNo !== '') {
        $sql .= ' AND chk_no = ?';
        $params[] = (int)$checkNo;
    }
    if ($minAmount !== null) {
        $sql .= ' AND ABS(chk_amount) >= ?';
        $params[] = $minAmount;
    }
    if ($maxAmount !== null) {
        $sql .= ' AND ABS(chk_amount) <= ?';
        $params[] = $maxAmount;
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
            $rowClass = $row[3] > 0 ? 'table-success' : '';
            $line = "<tr class=\"$rowClass\"><td>" .
                "<a href=\"edit_trans.php?acct=$acct&trans=" . (int)$row[0] . "\">" .
                date_to_str($row[4], '__mm__/__dd__/__yyyy__', false) .
                "</a></td>";
            $num = empty($row[2]) ? '-' : htmlentities((string)$row[2]);
            $line .= "<td>" . $num . "</td>";
            $line .= "<td>" . htmlentities($row[5]) . "</td>";
            $line .= "<td class=\"text-end\">" . sprintf('%.02f', $row[3]) . "</td>";
            $bal += $row[3];
            $line .= "<td class=\"text-end\">" . sprintf('%.02f', $bal) . "</td>";
            $reconciled = ($row[6] == 'Y');
            $badge = $reconciled
                ? '<span class="badge bg-success">R</span>'
                : '<span class="badge bg-secondary">-</span>';
            $line .= "<td>" . $badge . "</td>";
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
