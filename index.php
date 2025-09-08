<?php

include_once 'includes/config.php';
include_once 'includes/php-dbi.php';
include_once 'includes/functions.php';
include_once 'includes/connect.php';
include_once 'includes/ui.php';

include_once 'includes/translate.php';


// Get account info
$sql = "SELECT chk_acct_id, chk_bank, chk_name, chk_account_no, " .
  "chk_balance, chk_bank_balance " .
  "FROM chk_account ORDER BY chk_acct_id";
$res = dbi_query ( $sql );
$Accounts = array ();
if ( $res ) {
  while ( $row = dbi_fetch_row ( $res ) ) {
    $i = 0;
    $Account = array ();
    $Account['acct_id'] = $row[$i++];
    $Account['bank'] = $row[$i++];
    $Account['name'] = $row[$i++];
    $Account['account_no'] = $row[$i++];
    $Account['balance'] = $row[$i++];
    $Account['bank_balance'] = $row[$i++];
    $Accounts[] = $Account;
  }
  dbi_free_result ( $res );
} else {
  fatalError ( "Error in query:<br />$sql<br />" . dbi_error () );
}

print_header ( translate ("Accounts") );
print_heading ( translate ("Accounts") );

open_table ( array ( "Bank", "Acct Name", "Acct No", "Balance", "Bank Bal" ) );
for ( $i = 0; $i < count ( $Accounts ); $i++ ) {
  print "<tr>";
  $Account = $Accounts[$i];
  print "<td><a href=\"list.php?acct=" .
    $Account['acct_id'] . "\">" . htmlentities ( $Account['bank'] ) . "</a></td>";
  print_table_cell ( $Account['name'] );
  print_table_cell ( $Account['account_no'] );
  print_table_cell ( sprintf ( "%.02f", $Account['balance'] ) );
  print_table_cell ( sprintf ( "%.02f", $Account['bank_balance'] ) );
  print "</tr>\n";
}
close_table ();

print_trailer ( );
?>
