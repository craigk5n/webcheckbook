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

//print_account_info ( $Account );

?>

<form action="edit_account_handler.php" method="POST">
<input type="hidden" name="acct" value="<?php echo $acct; ?>" />

<table border="0">
<tr><td><b>Bank:</b></td>
  <td><input size="25" name="bank" value="<?php echo htmlentities($Account['bank'] ); ?>" /></td></tr>
 
<tr><td><b>Account Name:</b></td>
  <td><input size="25" name="name" value="<?php echo htmlentities($Account['name'] ); ?>" /></td></tr>
 
<tr><td><b>Account No.:</b></td>
  <td><input size="25" name="account_no" value="<?php echo htmlentities($Account['account_no'] ); ?>" /></td></tr>
 
<tr><td><b>Status:</b></td>
  <td><input type="radio" name="is_archived" value="N"
    <?php if ( empty ( $Account['is_archived'] ) || $Account['is_archived'] == 'N' ) { echo " checked=\"checked\""; } ?>
  />Active
  &nbsp;&nbsp;
  <input type="radio" name="is_archived" value="Y"
    <?php if ( ! empty ( $Account['is_archived'] ) && $Account['is_archived'] == 'Y' ) { echo " checked=\"checked\""; } ?>
  ?>Archived </td></tr>
 
</table>

<input type="submit" value="Save" />

</form>

<?php


print_trailer ();
?>
