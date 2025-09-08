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

$statement = getIntValue ( "statement" );
if ( empty ( $statement ) )
  fatalError ( "No statement specified" );

$daysBack = getIntValue ( "daysBack" );
if ( empty ( $daysBack ) )
  $daysBack = 60;

$seq = getIntValue ( "seq" );
if ( empty ( $seq ) ) {
  // calculate first not reconciled
  $res = dbi_query ( "SELECT chk_sequence FROM chk_bank_trans " .
    "WHERE chk_acct_id = $acct " .
    "AND chk_statement_id = $statement " .
    "AND chk_trans_id IS NULL " .
    "ORDER BY chk_sequence" );
  if ( $res ) {
    if ( $row = dbi_fetch_row ( $res ) ) {
      $seq = $row[0];
    }
    dbi_free_result ( $res );
  }
}
//echo "seq = $seq <p>";
if ( empty ( $seq ) ) {
  // All done, so just show first reconciled
  $res = dbi_query ( "SELECT chk_sequence FROM chk_bank_trans " .
    "WHERE chk_acct_id = $acct " .
    "AND chk_statement_id = $statement " .
    "ORDER BY chk_sequence" );
  if ( $res ) {
    if ( $row = dbi_fetch_row ( $res ) ) {
      $seq = $row[0];
    }
    dbi_free_result ( $res );
  }
}
//echo "seq = $seq <p>";

// count how many
$res = dbi_query ( "SELECT COUNT(*) FROM chk_bank_trans " .
  "WHERE chk_acct_id = $acct AND chk_statement_id = $statement" );
if ( ! $res )
  fatalError ( "Database error: " . dbi_error () );
$count = 0;
if ( $row = dbi_fetch_row ( $res ) ) {
  $count = $row[0];
}
dbi_free_result ( $res );
# Progress...
$res = dbi_query ( "SELECT COUNT(*) FROM chk_bank_trans " .
  "WHERE chk_acct_id = $acct AND chk_statement_id = $statement AND chk_trans_id IS NOT NULL" );
if ( ! $res )
  fatalError ( "Database error: " . dbi_error () );
$numDone = 0;
if ( $row = dbi_fetch_row ( $res ) ) {
  $numDone = $row[0];
}
dbi_free_result ( $res );

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

$allDone = ( $numDone == $count );

print_header ( translate("Reconcile Statement") );

$status = $allDone ? "Complete" : sprintf ( "%d of %d", $seq + 1, $count );

print_heading ( translate("Reconcile Statement") . " [" . $status . "]" );

print_account_info ( $Account );

$res = dbi_query ( "SELECT chk_statement_id, chk_description, " .
  "chk_start_date, chk_end_date " .
  "FROM chk_bank_statement " .
  "WHERE chk_acct_id = $acct " .
  "AND chk_statement_id = $statement " .
  "ORDER BY chk_end_date DESC" );
if ( ! $res ) {
  fatalError ( "Database error: " . dbi_error () );
}
$row = dbi_fetch_row ( $res );
if ( ! $row ) {
  fatalError ( "No such statement with id $statement" );
}
$startDate = date_to_str ( $row[2], "__mm__/__dd__/__yyyy__", false );
$endDate = date_to_str ( $row[3], "__mm__/__dd__/__yyyy__", false );
if ( $count > 0 ) {
  $progress = (int) ( $numDone * 100.0 / $count );
  $percent = sprintf ( "%.1f", $numDone * 100.0 / $count );
} else {
  $progress = 0;
  $percent = "n/a";
}
$statementName = $row[1];

// Sum up credits and debits of all transactions in the statement.
$sql = "SELECT SUM(chk_amount) FROM chk_bank_trans WHERE " .
  "chk_acct_id = ? AND chk_statement_id = ? AND chk_amount > 0.0";
$values = [ $acct, $statement ];
$res = dbi_execute ( $sql, $values );
if ( ! $res )
  fatalError ( "Database error: " . dbi_error () );
$row = dbi_fetch_row ( $res );
if ( ! $row )
  fatalError ( "No such statement with id $statement" );
$deposits = sprintf ( "%.2f", $row[0] );

$sql = "SELECT SUM(chk_amount) FROM chk_bank_trans WHERE " .
  "chk_acct_id = ? AND chk_statement_id = ? AND chk_amount < 0.0";
$values = [ $acct, $statement ];
$res = dbi_execute ( $sql, $values );
if ( ! $res )
  fatalError ( "Database error: " . dbi_error () );
$row = dbi_fetch_row ( $res );
if ( ! $row )
  fatalError ( "No such statement with id $statement" );
$withdrawals = sprintf ( "%.2f", 0.0 - $row[0] );

print "<br><table border=\"0\">\n" .
  "<tr><td>Statement:</td><td>" . htmlentities ( $statementName ) . "</td></tr>\n" .
  "<tr><td>Start Date:</td><td>" . $startDate . "</td></tr>\n" .
  "<tr><td>End Date:</td><td>" . $endDate . "</td></tr>\n" .
  "<tr><td>Depoits:</td><td>" . $deposits . "</td></tr>\n" .
  "<tr><td>Withdrawals:</td><td>" . $withdrawals . "</td></tr>\n" .
  "<tr><td valign=\"top\">Progress:</td><td>" . $numDone . ' of ' . $count . " (" . $percent ." %)" .
  '<div class="Progress">' .
    '<progress max="100" value="' . $progress . '" class="Progress-main" aria-labelledby="Progress-id">' .
    '<div class="Progress-bar" role="presentation">' .
    '<span class="Progress-value" style="width: ' . $progress . '%;">&nbsp;' .
    '</span>' .
    '</div>' .
    '</progress>' .
    '</div>' .
  "</td></tr>\n" .
  "</table>\n<br>";

// Load bank transaction if not done reconciling or
// if a sequence number was in the URL.
if ( $allDone && empty ( $seq ) ) {
  echo "<p>All transactions for this statement have been reconciled.</p>\n";
} else {
  $sql = "SELECT chk_no, chk_amount, chk_date, chk_description, " .
    "chk_memo, chk_trans_id, chk_sequence " .
    "FROM chk_bank_trans " .
    "WHERE chk_acct_id = $acct AND chk_statement_id = $statement " .
    ( ! empty ( $seq ) ? "AND chk_sequence = $seq " : '' ) .
    "ORDER BY chk_sequence";

  $res = dbi_query ( $sql );
  if ( ! $res )
    fatalError ( "Database error: " . dbi_error () );
  if ( $row = dbi_fetch_row ( $res ) ) {
    $trans = array (
      "no" => $row[0],
      "amount" => $row[1],
      "date" => $row[2],
      "description" => $row[3],
      "memo" => $row[4],
      "trans_id" => $row[5],
      "sequence" => $row[6] );
    print_bank_transaction ( $trans );
  
    if ( $trans['trans_id'] > 0 ) {
       // Previously reconciled...
    } else {
      $descPrior = get_description_from_prior_reconcile ( $acct, $trans['description'] );
      // Find matching transaction not yet reconciled...
      $matches = find_transactions ( $trans, $daysBack );
      // If nothing found in standard time window, go back 6 months...
      if ( count ( $matches ) == 0 )
        $matches = find_transactions ( $trans, 180 );

      print "<form action=\"reconcile_handler.php\" />\n";
      print "<input type=\"hidden\" name=\"acct\" value=\"$acct\" />\n";
      print "<input type=\"hidden\" name=\"statement\" value=\"$statement\" />\n";
      print "<input type=\"hidden\" name=\"seq\" value=\"$seq\" />\n";

      for ( $i = 0; $i < count ( $matches ); $i++ ) {
        // Only select by default if amount is correct AND the text of our description
        // is found in the bank transaction.
        $textFound = preg_match ( '/' . strtolower($matches[$i]['description']) . '/',
          strtolower($trans['description']) );
        if ( ! $textFound && ! empty ( $descPrior ) )
          $textFound = preg_match ( '/' . strtolower($matches[$i]['description']) . '/',
            strtolower($descPrior) );
        $chkNumMatches = $matches[$i]['no'] != '' &&
          $matches[$i]['no'] == $trans['no'];
        $selected = ( $matches[$i]['amount'] == $trans['amount'] ) && ( $textFound || $chkNumMatches );
        $enabled = ( $matches[$i]['amount'] == $trans['amount'] );
        print_transaction ( $matches[$i], $i == 0, $i == count ( $matches ) - 1,
          'trans_id', $matches[$i]['trans_id'], $selected, $enabled, ! $enabled );
      }

      if ( count ( $matches ) == 0 && empty ( $trans['trans_id'] ) ) {
        echo "<p><b>No similar transactions found!</b></p>\n";
      } else {
        if ( empty ( $trans['trans_id'] ) )
          print "<input type=\"submit\" value=\"Reconcile Selected\" />\n";
      }
  
      // Add new transaction and reconcile with it.
      // First see if we have previously reconciled a transaction with
      // the same description.
      $d = get_description_from_prior_reconcile ( $acct, $trans['description'] );
      if ( empty ( $d ) )
        $d = $trans['description'];
      //echo "<pre>"; print_r ( $trans ); echo "</pre>";
      echo "<br>";
      $desc = preg_replace ( '/\s+/', ' ', $trans['description'] );
      print "<p>Add New Transaction:</p>";
      $arr =  array ( translate("Date"), translate("Chk#"),
         translate("Amount"), translate("Description"), translate("Memo") );
      open_table ( $arr );
      print "<tr>";
      print_table_cell (
        '<input length="10" name="date" value="' .
        date_to_str ( $trans['date'], "__mm__/__dd__/__yyyy__" . '" />', false ), false );
      print_table_cell ( '<input length="6" name="no" value="' .
        ( empty ( $trans['no'] ) ? '' : $trans['no'] ) . '"/>', false );
      print_table_cell ( '<input length="8" name="amount" value="' .
      sprintf ( "%.2f", $trans['amount'] ) . '" />', false );
      print_table_cell ( '<input length="50" name="description" value="' .
        htmlentities ( $d ) . '" />', false );
      print_table_cell ( '<input length="20" name="memo" value="" />', false );
      print_table_cell ( '', false );
      print "</tr>\n";
      close_table ();
      print "<input name=\"add\" type=\"submit\" value=\"Add New &amp Reconcile\">\n";
      print "</form>\n";
    }
  } else {
    echo "Sequence $seq not found.\n";
  }
  dbi_free_result ( $res );
}

// If nothing left to reconcile, show a table of how everything was
// reconciled.  If not yet done, provide a link to show this.
$showTable = getIntValue('showTable');
if ( $allDone || $showTable == '1' ) {
  // TODO: unreconciled bank trans not being included
  $sql = 'SELECT b.chk_acct_id, b.chk_statement_id, b.chk_sequence, ' .
    'b.chk_no, b.chk_amount, b.chk_date, b.chk_description, ' .
    'b.chk_memo, b.chk_trans_id, ' .
    'a.chk_type, a.chk_no, a.chk_amount, a.chk_date, a.chk_description, ' .
    'a.chk_reconciled ' .
    'FROM chk_bank_trans AS b ' .
    'LEFT OUTER JOIN chk_trans a ON b.chk_trans_id = a.chk_trans_id ' .
    "WHERE a.chk_acct_id = $acct " .
    "AND b.chk_acct_id = $acct " .
    "AND b.chk_statement_id = $statement " .
    'ORDER BY b.chk_date ASC';
   $res = dbi_query ( $sql );
   $items = [ ];
   $arr = [ 'Bank Chk#', 'Bank Amount', 'Bank Date', 'Bank Description',
     'Chk#', 'Amount', 'Date', 'Description', 'Memo' ];
   open_table ( $arr );
   $rowNum = 0;
   while ( $row = dbi_fetch_row ( $res ) ) {
     $rowNum++;
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
     $items[] = $trans;
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
     print "<td class=\"$css\">" .
       ( date_to_str ( $trans['my.date'], "__mm__/__dd__/__yyyy__", false ) ) . "</td>";
     print "<td class=\"$css\">" .
       htmlentities ( $trans['my.description'] ) . "</td>";
     print "<td class=\"$css\">" .
       htmlentities ( $trans['my.memo'] ) . "</td>";
     print "</tr>";
   }
   close_table ();
   //echo "<pre>"; print_r ( $items ); print "</pre>\n";
} else {
  print "<br /><a href=\"" . $_SERVER['REQUEST_URI'] . "&showTable=1\">Show reconciled transactions</a>\n";
}

if ( $seq < ( $count - 1 ) ) {
  if ( ! $allDone ) {
    $res = dbi_query ( "SELECT chk_sequence FROM chk_bank_trans " .
      "WHERE chk_acct_id = $acct " .
      "AND chk_statement_id = $statement " .
      "AND chk_trans_id IS NULL " .
      "AND chk_sequence > $seq " .
      "ORDER BY chk_sequence" );
    echo "<br><br>";
    if ( $res ) {
      if ( $row = dbi_fetch_row ( $res ) ) {
        print "<br /><a href=\"reconcile.php?acct=$acct&statement=$statement&seq=" .
          $row[0] . "\">Next Bank Transaction (Not Reconciled)</a>\n";
      }
      dbi_free_result ( $res );
    }
  }
  print "<br /><a href=\"reconcile.php?acct=$acct&statement=$statement&seq=" .
    ( $seq + 1 ) . "\">Next Bank Transaction</a>\n";
}
if ( $seq > 0 ) {
  print "<br /><a href=\"reconcile.php?acct=$acct&statement=$statement&seq=" .
    ( $seq - 1 ) . "\">Previous Bank Transaction</a>\n";
}

print_trailer ();
?>
