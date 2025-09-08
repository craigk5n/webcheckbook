<?php

include_once 'includes/config.php';
include_once 'includes/php-dbi.php';
include_once 'includes/functions.php';
include_once 'includes/connect.php';
include_once 'includes/ui.php';

include_once 'includes/translate.php';

$acct = getIntValue ( "acct" );
if ( empty ( $acct ) )
  fatalError ( "No account specified" );

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

print_header ( translate("Statements") );

print_heading ( translate("Statements") );

print_account_info ( $Account );


$res = dbi_query ( "SELECT chk_statement_id, chk_description, " .
  "chk_start_date, chk_end_date " .
  "FROM chk_bank_statement " .
  "WHERE chk_acct_id = $acct " .
  "ORDER BY chk_end_date DESC" );
if ( ! $res ) {
  fatalError ( "Database error: " . dbi_error () );
}
echo "<ul>\n";
while ( $row = dbi_fetch_row ( $res ) ) {
  $statement = $row[0];
  $out = "<a href=\"reconcile.php?acct=$acct&statement=$row[0]\">" .
    htmlentities ( $row[1] ) . "</a>: " .
    date_to_str ( $row[2], "__mm__/__dd__/__yyyy__", false ) . ' - ' .
    date_to_str ( $row[3], "__mm__/__dd__/__yyyy__", false );
  $num_rec = $num_not_rec = 0;
  $res2 = dbi_query ( "SELECT COUNT(*) FROM chk_bank_trans " .
    "WHERE chk_acct_id = $acct AND chk_statement_id = $row[0] " .
    "AND chk_trans_id IS NULL" );
  if ( $res2 ) {
    if ( $row2 = dbi_fetch_row ( $res2 ) ) {
      $num_not_rec = $row2[0];
    }
    dbi_free_result ( $res2 );
  }
  $res2 = dbi_query ( "SELECT COUNT(*) FROM chk_bank_trans " .
    "WHERE chk_acct_id = $acct AND chk_statement_id = " . $statement );
  if ( $res2 ) {
    if ( $row2 = dbi_fetch_row ( $res2 ) ) {
      $num_rec = $row2[0];
    }
    dbi_free_result ( $res2 );
  }
  if ( $num_not_rec > 0 )
    print '<li><b>';
  else
    print '<li>';
  if ($num_not_rec == 0) {
    $out .= " (All $num_rec reconciled)";
  } else {
    $out .= " ($num_not_rec of $num_rec not reconciled)";
    // Allow delete if nothing reconciled
    if ($num_not_rec == $num_rec) {
      $out .=  ' <a onclick="return confirm(\'Are you sure you want to proceed to this URL?\');" href="statement_del.php?acct=' . $acct . '&statement=' . $statement . '"">Delete</a>';
    }
  }
  if ( $num_not_rec > 0 )
    $out .= "</b>";
  print $out . "</li>\n";
}
dbi_free_result ( $res );
echo "</ul>\n";

print_trailer ();
?>
