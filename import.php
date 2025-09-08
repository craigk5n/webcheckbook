<?php

include_once 'includes/config.php';
include_once 'includes/php-dbi.php';
include_once 'includes/functions.php';
include_once 'includes/connect.php';
include_once 'includes/ui.php';

include_once 'includes/translate.php';

$acct = getIntValue ( "acct" );

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

print_header ( translate("Import") );

print_heading ( translate("Import") );

print_account_info ( $Account );

?>

<form action="import_handler.php" method="post" name="importform" enctype="multipart/form-data">
<input type="hidden" name="acct" value="<?php echo $acct; ?>" />

<table style="border-width:0px;">

<tr><td style="font-weight:bold;"><?php etranslate("Description")?>:</td>
<td><input type="text" size="30" name="description" /></td></tr>

<tr><td style="font-weight:bold;"><?php etranslate("CSV File")?>:</td>
  <td><input type="file" name="FileName" size="45" maxlength="50" /></td></tr>
<tr><td colspan="2"><input type="submit" value="<?php etranslate("Import")?>" />
</td></tr>

</table>

</form>

<?php
print_trailer ();
?>
