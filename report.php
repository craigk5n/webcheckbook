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

print_header ( translate("Account") . ": " . $Account['name'] );

print_heading ( translate("Search") . " " . translate("Account") . ": " . $Account['name'] );

print_account_info ( $Account );

$start = getValue ( "start" );
$end = getValue ( "end" );
$search = getValue ( "search" );
$rec = getValue ( "reconciled" );

$sql = "SELECT chk_no FROM chk_trans " .
  "WHERE chk_acct_id = ? " .
  "ORDER BY chk_no ASC";
$params = [ $acct ];
$res = dbi_execute ( $sql, $params );
if ( ! $res )
  fatalError ( "Error in query:<br />$sql<br />" . dbi_error () );
$first = true;
$lastNum = -1;
$missing = array ();
$dups = array ();
$found = array ();
$first = 0;
$last = 0;
while ( $row = dbi_fetch_row ( $res ) ) {
  $thisNum = $row[0];
  $last = $thisNum;
  if ( $first == 0 )
    $first = $thisNum;
  if ( $lastNum > 0 ) {
    // Compare to previous num
    if ( $thisNum == $lastNum )
      $dups[] = $thisNum;
  }
  $found[] = $thisNum;
  $lastNum = $row[0];
}
dbi_free_result ( $res );
for ( $i = $first; $i <= $last; $i++ ) {
  if ( ! in_array ( $i, $found ) ) {
    $missing[] = $i;
  }
}

?>
<h3>Duplicate Check Numbers</h3>
<ul>

<?php
if ( count ( $dups ) == 0 )
  echo "<li>None</li>\n";
for ( $i = 0; $i < count ( $dups ); $i++ ) {
  print "<li> " . $dups[$i] . " </li>\n";
}
?>
</ul>

<h3>Missing Check Numbers</h3>
<ul>
<?php
if ( count ( $missing ) == 0 )
  echo "<li>None</li>\n";
for ( $i = 0; $i < count ( $missing ); $i++ ) {
  print "<li> " . $missing[$i] . " </li>\n";
}
?>
</ul>

<h3>Unreconciled Transactions</h3>
<?php

// Get posting date of most recent reconciled transaction.
$sql = "SELECT MAX(chk_date) FROM chk_trans " .
  "WHERE chk_reconciled = 'Y'";
$res = dbi_query ( $sql );
if ( ! $res )
  fatalError ( "Error in query:<br />$sql<br />" . dbi_error () );
$row = dbi_fetch_row ( $res );
$lastDate = $row[0];
if ( empty ( $lastDate ) ) {
  echo "<p>Nothing reconciled yet.</p>";
} else {
  // Look for transactions that are older than the most
  // recent bank transaction that has been reconciled.

  ?><p>Showing transactions before <b><?php
  echo date_to_str ( $lastDate, "__mm__/__dd__/__yyyy__", false );
  echo "</b></p>\n";

  $sql = "SELECT chk_trans_id, chk_type, chk_no, chk_amount, chk_date, " .
    "chk_description " .
    "FROM chk_trans " .
    "WHERE chk_reconciled = 'N' AND " .
    "chk_date <= $lastDate " .
    "AND chk_acct_id = $acct " .
    "ORDER BY chk_date DESC";
  //echo "SQL: $sql <br />\n";
  $res = dbi_query ( $sql );
  if ( ! $res )
    fatalError ( "Error in query:<br />$sql<br />" . dbi_error () );
  $items = array ();
  $cnt = 0;
  while ( $row = dbi_fetch_row ( $res ) ) {
    $trans = array (
      "account" => $acct,
      "trans_id" => $row[0],
      "type" => $row[1],
      "no" => $row[2],
      "amount" => $row[3],
      "date" => $row[4],
      "description" => $row[5]
      );
    // Ignore if amount is 0.0
    if ( $trans['amount'] != 0.0 ) {
      $cnt++;
      print_transaction ( $trans, $cnt == 1, false );
    }
  }
 dbi_free_result ( $res );
 close_table ();
}

echo "<h3>Incorrect Reconciled Amounts</h3>\n";

$sql = 'SELECT b.chk_acct_id, b.chk_statement_id, b.chk_sequence, ' .
  'b.chk_no, b.chk_amount, b.chk_date, b.chk_description, ' .
  'b.chk_memo, b.chk_trans_id, ' .
  'a.chk_type, a.chk_no, a.chk_amount, a.chk_date, a.chk_description, ' .
  'a.chk_reconciled ' .
  'FROM chk_bank_trans AS b ' .
  'LEFT OUTER JOIN chk_trans a ON b.chk_trans_id = a.chk_trans_id ' .
  "WHERE a.chk_acct_id = $acct " .
  "AND a.chk_amount <> b.chk_amount " .
  "AND b.chk_acct_id = $acct " .
  'ORDER BY b.chk_date ASC';
$res = dbi_query ( $sql );
$items = [ ];
$arr = [ 'Bank Chk#', 'Bank Amount', 'Bank Date', 'Bank Description',
  'Chk#', 'Amount', 'Date', 'Description', 'Memo' ];
open_table ( $arr );
$rowNum = 0;
while ( $row = dbi_fetch_row ( $res ) ) {
  $i = 0;
  $trans = array (
    "bank.acct_id" => $row[$i++],
    "bank.b.chk_statement_id" => $row[$i++],
    "bank.b.chk_sequence" => $row[$i++],
    "bank.chk_no" => $row[$i++],
    "bank.amount" => $row[$i++],
    "bank.date" => $row[$i++],
    "bank.description" => $row[$i++],
    "bank.memo" => $row[$i++],
    "bank.trans_id" => $row[$i++],
    "my.chk_type" => $row[$i++],
    "my.chk_no" => $row[$i++],
    "my.amount" => $row[$i++],
    "my.date" => $row[$i++],
    "my.description" => $row[$i++],
    "my.reconciled" => $row[$i++] );
  if ( abs($trans['bank.amount'] - $trans['my.amount']) < 0.009 )
    continue; // round error
  $rowNum++;
  $items[] = $trans;
  $url = "edit_trans.php?acct=" . $acct . "&trans=" . $trans['bank.trans_id'];
  print "<tr>";
  $odd = ( $rowNum % 2 > 0 ) ? "odd" : "even";
  $css = ( $trans['bank.amount'] > 0.0 ) ? "deposit" : "withdrawal-$odd";
  print "<td class=\"$css\" align=\"right\">" .
    ( empty ( $trans['bank.chk_no'] ) ? '-' : $trans['bank.chk_no'] ) . "</td>";
  print "<td class=\"$css\" align=\"right\">" .
    sprintf ( "%.2f", $trans['bank.amount'], 100.0 ) . "</td>";
  print "<td class=\"$css\">" .
    date_to_str ( $trans['bank.date'], "__mm__/__dd__/__yyyy__", false ) . "</td>";
  print "<td class=\"$css\">" .
    htmlentities ( $trans['bank.description'] ) . "</td>";
  print "<td class=\"$css\" align=\"right\">" .
    ( empty ( $trans['my.chk_no'] ) ? '-' : $trans['my.chk_no'] ) . "</td>";
  print "<td class=\"$css\" align=\"right\">" .
    ( sprintf ( "%.2f", $trans['my.amount'], 100.0 ) ) . "</td>";
  print "<td class=\"$css\"><a href=\"$url\">" .
    ( date_to_str ( $trans['my.date'], "__mm__/__dd__/__yyyy__", false ) ) . "</a></td>";
  print "<td class=\"$css\">" .
    htmlentities ( $trans['my.description'] ) . "</td>";
  print "<td class=\"$css\">" .
    htmlentities ( $trans['my.memo'] ) . "</td>";
  print "</tr>\n";
}
close_table ();

print_trailer ();
?>
