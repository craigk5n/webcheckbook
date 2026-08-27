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
$num = 15;

$Account = get_account_info($acct);

// Get MAX check number
$res = dbi_execute('SELECT MAX(chk_no) FROM chk_trans WHERE chk_acct_id = ?', [$acct]);
if (!$res)
    fatalError('Error in query: ' . dbi_error());
$maxCheck = '';
if (($row = dbi_fetch_row($res)) && $row[0] > 99) {
    $maxCheck = (string)$row[0];
}
dbi_free_result($res);

print_header(translate('Account') . ': ' . $Account['name']);
print_heading(translate('Account') . ': ' . $Account['name']);
print_account_info($Account);

?>

<p><strong>Last check number:</strong> <?php echo htmlentities($maxCheck); ?></p>

<form action="add_trans_handler.php" method="POST">
<input type="hidden" name="acct" value="<?php echo $acct; ?>" />

<div class="table-responsive">
<table class="table table-sm table-bordered add_transactions_table" id="add_transactions_table">
<thead class="table-dark">
<tr><th>Date</th><th>Type</th><th>ChkNo</th><th>Description</th><th>Amount</th></tr>
</thead>
<tbody>
<?php
for ($i = 0; $i < $num; $i++) {
    echo "<tr><td><input type=\"text\" class=\"form-control form-control-sm date\" id=\"date_$i\" name=\"date_$i\" ";
    if ($i == 0)
        echo "value=\"" . date("m/d/Y") . "\" ";
    echo "onfocus=\"steal_date(this.form,$i,$num)\" ";
    echo "onBlur=\"this.value = clean_date(this.value)\" ";
    echo "/></td>\n";
    echo "<td><select class=\"form-select form-select-sm\" name=\"type_$i\"><option value=\"2\">Debit<option value=\"3\">Check<option value=\"4\">Charge/Fee<option value=\"1\">Deposit</select></td>";
    echo "<td><input type=\"text\" class=\"form-control form-control-sm\" id=\"num_$i\" name=\"num_$i\" ";
    echo "onfocus=\"suggest_check_number(this.form,$i,$num)\" ";
    echo "/></td>\n";
    echo "<td>";
    echo '<div class="autocomplete">';
    echo "<input type=\"text\" class=\"form-control form-control-sm autocomplete\" autocomplete=\"off\" id=\"description_$i\" name=\"description_$i\" " .
        "onBlur=\"this.value = this.value.toUpperCase();\" placeholder=\"Description\" /></div></td>\n";
    echo "<td><input type=\"text\" class=\"form-control form-control-sm\" name=\"amount_$i\" onFocus=\"onFocusAmount(this.form,$i);\" /></td>\n";
    echo "</tr>\n";
}
?>
</tbody>
</table>
</div>

<button type="submit" class="btn btn-primary">Add</button>
</form>

<script src="js/checkbook.js"></script>
<script>
const APP_CONFIG = {
    acct: <?php echo $acct; ?>,
    maxCheck: "<?php echo $maxCheck; ?>",
    numRows: <?php echo $num; ?>,
    names: [
<?php
$sql = 'SELECT count(chk_description), chk_description FROM chk_trans ' .
    'WHERE chk_acct_id = ? GROUP BY chk_description ORDER BY count(chk_description) DESC LIMIT 1000';
$res = dbi_execute($sql, [$acct]);
if ($res) {
    $j = 0;
    while ($row = dbi_fetch_row($res)) {
        if ($j++ > 0) echo ",\n";
        echo '        "' . addslashes($row[1]) . '"';
    }
    dbi_free_result($res);
}
?>
    ]
};
initAddTransactions(APP_CONFIG);
</script>

<?php
print_trailer();
?>
