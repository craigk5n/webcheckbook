<?php

include_once 'includes/config.php';
include_once 'includes/php-dbi.php';
include_once 'includes/functions.php';
include_once 'includes/connect.php';
include_once 'includes/ui.php';

include_once 'includes/translate.php';

$acct = getIntValue ( "acct" );
if ( empty ( $acct ) ) {
  fatalError ( "No account specified" );
}
$trans = getIntValue ( "trans" );
if ( empty ( $trans ) ) {
  fatalError ( "No transaction specified" );
}

$date = getValue ( "date" );
$dateAr = preg_split ( "/[\/\-]/", $date );
if ( empty ( $dateAr[2] ) )
  $dateAr[2] = date ( "Y" );
else {
  if ( $dateAr[2] < 100 )
    $dateAr[2] += 2000;
}
$dateStr = sprintf ( "%04d%02d%02d", $dateAr[2], $dateAr[0], $dateAr[1] );
$type = getValue ( "type" );
$num = getValue ( "num" );
if ( $type != 3 )
  $num = 'NULL';
$description = getValue ( "description" );
$amount = (float)getValue ( "amount" );
if ( $type != 1 ) {
  // not a deposit
  $amount = 0 - $amount;
}
$description = strtoupper ( $description );

$sql = "UPDATE chk_trans SET " .
  "chk_type = $type, " .
  "chk_no = $num, " .
  "chk_amount = $amount, " .
  "chk_date = $dateStr, " .
  "chk_description = '$description' " .
  "WHERE chk_acct_id = $acct AND chk_trans_id = $trans";
//echo "SQL: $sql <br />";
//exit;
if ( ! dbi_query ( $sql ) ) {
  fatalError ( "Database error: " . dbi_error () );
}
  
update_balances ( $acct );

#print_heading ( translate("Account") . ": " . $Account['name'] );
#print_account_info ( $Account );

do_redirect ( "list.php?acct=$acct" );
?>
