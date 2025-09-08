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
  "FROM chk_account ORDER BY chk_is_archived, chk_acct_id";
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

//multi_sort ( $Accounts, $key = 'end_date' );

for ( $i = 0; $i < count ( $Accounts ); $i++ ) {
  // Get first and last transaction date.
  $sql = 'SELECT MIN(chk_date), MAX(chk_date) FROM chk_trans ' .
    'WHERE chk_acct_id = ?';
  $res = dbi_execute ( $sql, [ $Accounts[$i]['acct_id'] ] );
  if ( $res ) {
    if ( $row = dbi_fetch_row ( $res ) ) {
      $Accounts[$i]['start_date'] = $row[0];
      $Accounts[$i]['end_date'] = $row[1];
    }
  }
}

//echo "<pre>"; print_r ( $Accounts ); echo "</pre>\n";

print_header ( translate ("Accounts") );
print_heading ( translate ("Accounts") );

open_table ( array ( "Bank", "Acct Name", "Acct No", "Start Date", "End Date", "Balance", "Bank Bal" ) );
for ( $i = 0; $i < count ( $Accounts ); $i++ ) {
  print "<tr>";
  $Account = $Accounts[$i];
  print_table_cell ( $Account['bank'] );
  print "<td><a href=\"list.php?acct=" .
    $Account['acct_id'] . "\">" . htmlentities ( $Account['name'] ) . "</a></td>";
  print_table_cell ( $Account['account_no'] );
  print_table_cell ( date_to_str ( $Account['start_date'], "__mm__/__dd__/__yyyy__", false ) );
  print_table_cell ( date_to_str ( $Account['end_date'], "__mm__/__dd__/__yyyy__", false ) );
  print_table_cell ( sprintf ( "%.02f", $Account['balance'] ) );
  print_table_cell ( sprintf ( "%.02f", $Account['bank_balance'] ) );
  print "</tr>\n";
}
close_table ();

print_trailer ( );


function multi_sort($array, $akey)
{  
  function compare($a, $b)
  {
     global $key;
     return strcmp($a[$key], $b[$key]);
  } 
  usort($array, "compare");
  return $array;
}

?>
