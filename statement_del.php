<?php
include_once 'includes/config.php';
include_once 'includes/php-dbi.php';
include_once 'includes/functions.php';
include_once 'includes/connect.php';
include_once 'includes/ui.php';

include_once 'includes/translate.php';

$sequence = 0;

$error = '';
$startDate = $endDate = '';
$acct = getIntValue ( "acct" );
$statement = getIntValue ( "statement" );
if ( empty ( $acct ) ) {
  fatalError ( "No account specified" );
}
if ( empty ( $statement ) ) {
  fatalError ( "No statement specified" );
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


$error = '';

// Delete transactions
$res = dbi_execute ("DELETE FROM chk_bank_trans " .
  "WHERE chk_acct_id = ? AND chk_statement_id = ?",
  [$acct, $statement] );
if (!$res) {
  $error = "Error in query:<br />$sql<br />" . dbi_error ();
}

// Delete statement
if (empty($error)) {
  $res = dbi_execute ("DELETE FROM chk_bank_statement " .
    "WHERE chk_acct_id = ? AND chk_statement_id = ?",
    [$acct, $statement] );
  if (!$res) {
    fatalError ( "Error in query:<br />$sql<br />" . dbi_error () );
  }
}

if (!empty($error)) {
  print_header ( "Delete Statement Results" );
  print_heading ( "Delete Statement Results" );
  echo "<h2>Error</h2>\n$error\n";
  print_trailer ();
} else {
  // Success
  do_redirect('statements.php?acct=' . $acct);
}


// end


function add_transaction ( $date, $amt, $no, $desc, $memo )
{
  global $acct, $statement_id, $sequence, $startDate, $endDate;

  print "<br /><br /><b>Date:</b> $date <br />";
  print "<b>Amount:</b> $amt <br />";
  print "<b>No:</b> $no <br />";
  print "<b>Desc:</b> $desc <br />";
  print "<b>Memo:</b> $memo <br />";

  if ( empty ( $no ) )
    $no = 'NULL';

  if ( $date < $startDate || empty ( $startDate ) )
    $startDate = $date;
  if ( $date > $endDate || empty ( $endDate ) )
    $endDate = $date;

  $desc = str_replace ( "'", "", $desc );
  $memo = str_replace ( "'", "", $memo );
  $sql = "INSERT INTO chk_bank_trans ( chk_acct_id, chk_statement_id, " .
    "chk_sequence, chk_no, chk_amount, chk_date, chk_description, " .
    "chk_memo ) VALUES ( " .
    "$acct, $statement_id, " .
    "$sequence, $no, $amt, $date, '$desc', " .
    "'$memo' )";

  //echo "$sql <br /> <br />";

  if ( ! dbi_query ( $sql ) ) {
    $error .= dbi_error () . "<br /><br />";
  }
   
  $sequence++;
}

?>
