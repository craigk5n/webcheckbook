<?php

include_once 'includes/config.php';
include_once 'includes/php-dbi.php';
include_once 'includes/functions.php';
include_once 'includes/connect.php';
include_once 'includes/ui.php';

include_once 'includes/translate.php';

$acct = getIntValue ( "acct" );
$trans = getIntValue ( "trans" );
if ( empty ( $acct ) ) {
  fatalError ( "No account specified" );
}
if ( empty ( $trans ) ) {
  fatalError ( "No transaction specified" );
}

// Get account info
$sql = "SELECT chk_bank, chk_name, chk_account_no, " .
  "chk_balance, chk_bank_balance " .
  "FROM chk_account WHERE chk_acct_id = $acct";
$res = dbi_query ( $sql );
$Account = array ();
if ( $res ) {
  $row = dbi_fetch_row ( $res );
  if ( $row ) {
    $Account['acct_id'] = $acct;
    $Account['bank'] = $row[0];
    $Account['name'] = $row[1];
    $Account['account_no'] = $row[2];
    $Account['balance'] = $row[3];
    $Account['bank_balance'] = $row[4];
    dbi_free_result ( $res );
  } else {
    fatalError ( "No such acct: $acct" );
  }
} else {
  fatalError ( "Error in query:<br />$sql<br />" . dbi_error () );
}
// Get first and last transaction date.
$sql = 'SELECT MIN(chk_date), MAX(chk_date) FROM chk_trans ' .
  'WHERE chk_acct_id = ?';
$res = dbi_execute ( $sql, [ $acct ] );
if ( $res ) {
  if ( $row = dbi_fetch_row ( $res ) ) {
    $Account['start_date'] = $row[0];
    $Account['end_date'] = $row[1];
  }
}

print_header ( translate("Account") . ": " . $Account['name'] );

print_heading ( translate("Account") . ": " . $Account['name'] );

print_account_info ( $Account );

$res = dbi_query ( "SELECT chk_type, chk_no, chk_amount, chk_date, " .
  "chk_description, chk_reconciled FROM chk_trans " .
  "WHERE chk_acct_id = $acct AND chk_trans_id = $trans" );
if ( $res ) {
  if ( $row = dbi_fetch_row ( $res ) ) {
    $type = $row[0];
    $num = $row[1];
    $amount = $row[2];
    $date = $row[3];
    $description = $row[4];
    $reconciled = $row[5];
    if ( $type != 1 )
      $amount = 0 - $amount;
  } else {
    fatalError ( "Could not find transaction id $trans" );
  }
  dbi_free_result ( $res );
} else {
  fatalError ( "Database error: " . dbi_error () );
}

?>

<form action="edit_trans_handler.php" method="POST">
<input type="hidden" name="acct" value="<?php echo $acct; ?>" />
<input type="hidden" name="trans" value="<?php echo $trans; ?>" />

<table border="0">
<tr><th>Date</th><th>Type</th><th>ChkNo</th><th>Description</th><th>Amount</th></tr>
<?php
  echo "<tr><td><input size=\"11\" name=\"date\" value=\"" .
    date_to_str ( $date, "__mm__/__dd__/__yyyy__", false ) .
    "\" /></td>\n";
  echo "<td><select name=\"type\">" .
    "<option value=\"2\"" . ( $type == 2 ? " selected" : "" ) . ">Debit" .
    "<option value=\"3\"" . ( $type == 3 ? " selected" : "" ) . ">Check" .
    "<option value=\"4\"" . ( $type == 4 ? " selected" : "" ) . ">Charge/Fee" .
    "<option value=\"1\"" . ( $type == 1 ? " selected" : "" ) . ">Deposit" .
    "</select></td>";
  echo "<td><input size=\"7\" name=\"num\" value=\"$num\" /></td>\n";
  echo "<td><input size=\"40\" name=\"description\" value=\"" .
    htmlentities ( $description ) . "\" /></td>\n";
  echo "<td><input size=\"8\" name=\"amount\" value=\"" .
    sprintf ( "%.02f", $amount ) . "\" /></td>\n";
  echo "</tr>\n";
?>
</table>

<?php if ( $reconciled == 'Y' ) { ?>
<p><b>NOTE:</b> You cannot edit a transaction that has been reconciled.</p>
<?php } else { ?>
<input type="submit" value="Save" />
<?php } ?>
</form>

<?php


print_trailer ();
?>
