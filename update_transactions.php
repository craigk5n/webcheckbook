<?php

include_once 'includes/config.php';
include_once 'includes/php-dbi.php';
include_once 'includes/functions.php';
include_once 'includes/connect.php';
include_once 'includes/ui.php';

include_once 'includes/translate.php';

$NUM_DISPLAY = 50;

$first = getIntValue ( "first" );
$acct = getIntValue ( "acct" );
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

print_header ( translate("Account") . ": " . $Account['name'] );

print_heading ( translate("Account") . ": " . $Account['name'] );

update_balances ( $acct );

print_account_info ( $Account );

$res = dbi_query ( "SELECT chk_trans_id, chk_description " .
  "FROM chk_trans WHERE chk_acct_id = $acct" );
if ( ! $res )
  fatalError ( "Error in query:<br />$sql<br />" . dbi_error () );
$i = 0;
while ( $row = dbi_fetch_row ( $res ) ) {
  $id = $row[0];
  $name = $row[1];
  $name = strtoupper($name);
  $params = [ $name, $acct, $id ];
  if ( ! dbi_execute ( 'UPDATE chk_trans SET chk_description = ? ' .
    'WHERE chk_acct_id = ? AND chk_trans_id = ?', $params ) ) {
    fatalError ( "Error in query:<br />$sql<br />" . dbi_error () );
  } else {
    $i++;
  }
}
dbi_free_result ( $res );

echo "<p>$i records updated.</p>\n";

?>

<?php
print_trailer ();
?>
