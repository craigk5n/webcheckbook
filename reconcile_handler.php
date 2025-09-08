<?php
include_once 'includes/config.php';
include_once 'includes/php-dbi.php';
include_once 'includes/functions.php';
include_once 'includes/connect.php';
include_once 'includes/ui.php';

include_once 'includes/translate.php';

$error = '';
$acct = getIntValue ( "acct" );
if ( empty ( $acct ) )
  fatalError ( "No account specified" );

$statement = getIntValue ( "statement" );
if ( empty ( $statement ) )
  fatalError ( "No statement specified" );

$seq = getIntValue ( "seq" );
if ( ! isset ( $seq ) )
  fatalError ( "No sequence specified" );
if(empty($seq))
  $seq = '0';

$adding = false;
if ( getValue ( 'add' ) != '' ) {
  // Add new transaction, then reconcile with it.
  $adding = true;
}
//echo "Adding = " . ( $adding ? "Y" : "N" ) . "<br>";

$trans_id = getIntValue ( "trans_id" );
if ( empty ( $trans_id ) && ! $adding )
  fatalError ( "You must select a transaction" );

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

// Add new transaction first
if ( $adding ) {
  // Get next transaction id for account.
  $next_id = 1;
  $res = dbi_query ( "SELECT MAX(chk_trans_id) FROM chk_trans " .
    "WHERE chk_acct_id = $acct" );
  if ( $res ) {
    if ( $row = dbi_fetch_row ( $res ) ) {
      $next_id = $row[0] + 1;
    }
    dbi_free_result ( $res );
  }
  $trans_id = $next_id;

  $date = getValue ( "date" );
  $dateAr = preg_split ( "/[\/\-]/", $date );
  if ( empty ( $dateAr[2] ) )
    $dateAr[2] = date ( "Y" );
  else {
    if ( $dateAr[2] < 100 )
      $dateAr[2] += 2000;
  }
  $dateStr = sprintf ( "%04d%02d%02d", $dateAr[2], $dateAr[0], $dateAr[1] );
  $num = getValue ( "num" );
  $description = getValue ( "description" );
  $amount = (float) getValue ( "amount" );
  if ( $amount < 0 ) {
    if ( $num != '' )
      $type = 3; // check
    else
      $type = 2; // debit
    if ( $amount > 0.0 )
      $amount = 0 - $amount;
  } else {
    $type = 1; // deposit
  }
  if ( $num == '' )
    $num = NULL;
  $description = strtoupper ( $description );
  $values = [ $acct, $next_id, $type, $num, $amount, $dateStr, $description, 'N' ];
  $sql = "INSERT INTO chk_trans " .
    "(chk_acct_id, chk_trans_id, chk_type, chk_no, chk_amount, chk_date, chk_description, chk_reconciled) " .
    "VALUES ( ?, ?, ?, ?, ?, ?, ?, ?)";
  if ( ! dbi_execute ( $sql, $values ) ) {
    fatalError ( "Database error: " . dbi_error () );
    exit;
  }
}

// Update transaction first
$sql = "UPDATE chk_trans SET chk_reconciled = 'Y' " .
  "WHERE chk_acct_id = $acct " .
  "AND chk_trans_id = $trans_id";

//echo "$sql <p>";
if ( ! dbi_query ( $sql ) )
  fatalError ( "Database error: " . dbi_error () );

// Update bank transaction now
$sql = "UPDATE chk_bank_trans SET chk_trans_id = $trans_id " .
  "WHERE chk_acct_id = $acct " .
  "AND chk_statement_id = $statement " .
  "AND chk_sequence = $seq";

//echo "$sql <p>";
if ( ! dbi_query ( $sql ) )
  fatalError ( "Database error: " . dbi_error () );


// Update balances...
update_balances ( $acct );

// Get next transaction not reconciled
$sql = "SELECT chk_sequence FROM chk_bank_trans " .
  "WHERE chk_acct_id = $acct " .
  "AND chk_statement_id = $statement " .
  "AND chk_sequence > $seq " .
  "ORDER BY chk_sequence";
//echo "$sql <p>\n";
if ( ! dbi_query ( $sql ) )
  fatalError ( "Database error: " . dbi_error () );

$res = dbi_query ( $sql );
if ( ! $res )
  fatalError ( "Database error: " . dbi_error () );
if ( $row = dbi_fetch_row ( $res ) ) {
  $url = "reconcile.php?acct=$acct&statement=$statement&seq=$row[0]";
  //echo "URL: $url <br>";
  dbi_free_result ( $res );
  do_redirect ( $url );
} else {
  dbi_free_result ( $res );
  //echo "No more transactions to reconcile...";
  $url = "reconcile.php?acct=$acct&statement=$statement";
  do_redirect ( $url );
}

echo "Done";
