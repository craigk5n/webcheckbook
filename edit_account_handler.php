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

$bank = getValue ( "bank" );
$name = getValue ( "name" );
$number = getValue ( "account_no" );
$is_archived = getValue ( "is_archived" );
if ( $is_archived != 'Y' )
  $is_archived = 'N';
 
$sql = "UPDATE chk_account SET " .
  "chk_bank = ?, chk_name = ?, chk_account_no = ?, chk_is_archived = ? " .
  "WHERE chk_acct_id = ?";
$params = [ $bank, $name, $number, $is_archived, $acct ];
//echo "SQL: $sql <br />";
//exit;
if ( ! dbi_execute ( $sql, $params ) ) {
  fatalError ( "Database error: " . dbi_error () );
}
  
update_balances ( $acct );

#print_heading ( translate("Account") . ": " . $Account['name'] );
#print_account_info ( $Account );

do_redirect ( "list.php?acct=$acct" );
?>
