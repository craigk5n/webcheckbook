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

//print_header ( translate("Account") . ": " . $Account['name'] );

$next_id = 1;
$res = dbi_query ( "SELECT MAX(chk_trans_id) FROM chk_trans " .
  "WHERE chk_acct_id = $acct" );
if ( $res ) {
  if ( $row = dbi_fetch_row ( $res ) ) {
    $next_id = $row[0] + 1;
  }
  dbi_free_result ( $res );
}

for ( $i = 0; $i < 99; $i++ ) {
  $date = getValue ( "date_$i" );
  $dateAr = preg_split ( "/[\/\-]/", $date );
  if ( empty ( $dateAr[2] ) )
    $dateAr[2] = date ( "Y" );
  else {
    if ( $dateAr[2] < 100 )
      $dateAr[2] += 2000;
  }
  $dateStr = sprintf ( "%04d%02d%02d", $dateAr[2], $dateAr[0], $dateAr[1] );
  $description = getValue ( "description_$i" );
  if ( ! empty ( $date ) && ! empty ( $description ) ) {
    $type = getValue ( "type_$i" );
    $num = getValue ( "num_$i" );
    if ( $type != 3 )
      $num = 'NULL';
    $description = getValue ( "description_$i" );
    $amount = getValue ( "amount_$i" );
    if ( $type != 1 ) {
      // not a deposit
      $amount = 0 - $amount;
    }
    $description = strtoupper ( $description );
    $sql = "INSERT INTO chk_trans VALUES ( " .
      "$acct, $next_id, $type, $num, $amount, $dateStr, " .
      "'$description', 'N' );";
    $next_id++;
    //echo "<p><b>SQL:</b> $sql </p>\n";
    if ( ! dbi_query ( $sql ) ) {
      fatalError ( "Database error: " . dbi_error () );
    }
  }
  
}

update_balances ( $acct );

#print_heading ( translate("Account") . ": " . $Account['name'] );
#print_account_info ( $Account );

do_redirect ( "add_trans.php?acct=$acct" );
?>
