<?php

include_once 'includes/config.php';
include_once 'includes/php-dbi.php';
include_once 'includes/functions.php';
include_once 'includes/connect.php';
include_once 'includes/ui.php';

include_once 'includes/translate.php';

$NUM_DISPLAY = 50;

$first = getIntValue ( "first" );
$acct = getIntValue ( "acct" );
if ( empty ( $acct ) ) {
  fatalError ( "No account specified" );
}

$doUpdate = getIntValue ( 'update' );

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

update_balances ( $acct );

print_header ( translate("Account") . ": " . $Account['name'] );

print_heading ( translate("Account") . ": " . $Account['name'] );

print_account_info ( $Account );

echo '<p><a href="edit_account.php?acct=' .
  $acct . '">Edit Account</a></p>';

$sql = "SELECT chk_trans_id, chk_type, chk_no, chk_amount, " .
  "chk_date, chk_description, chk_reconciled " .
  "FROM chk_trans " .
  "WHERE chk_acct_id = $acct " .
  "ORDER BY chk_date ASC";

$res = dbi_query ( $sql );
open_table ( array ( "Date", "Chk#", "Comment", "Amount", "Balance", " ", "Bank Bal" ) );
$bal = 0.0;
$bankBal = 0.0;
if ( $res ) {
  $out = array ();
  $cnt = 0;
  $lastDate = '';
  while ( $row = dbi_fetch_row ( $res ) ) {
    $cnt++;
    if ( $cnt == 0 || $row[4] != $lastDate ) {
      $out[] = "<tr><td colspan=\"7\" style=\"height: 1px; background-color: #000;\"></td></tr>\n";
    }
    if ( $row[3] > 0 ) {
      $class = "deposit";
    } else {
      $class = ( $cnt % 2 == 0 ) ? "withdrawal-even" : "withdrawal-odd";
    }
    $line = "<tr><td class=\"$class\">" .
      "<a href=\"edit_trans.php?acct=$acct&trans=$row[0]\">" .
      date_to_str ( $row[4], "__mm__/__dd__/__yyyy__", false ) .
      "</a></td>";
    $line .= "<td class=\"$class\">" . ( empty ( $row[2] ) ? "-" : $row[2] ) . "</td>";
    $line .= "<td class=\"$class\">" . htmlentities ( $row[5] ) . "</td>";
    $line .= "<td class=\"$class\" align=\"right\">" . ( sprintf ( "%.02f", $row[3] ) ) . "</td>";
    $bal += $row[3];
    $line .= "<td class=\"$class\" align=\"right\">" . ( sprintf ( "%.02f", $bal ) ) . "</td>";
    if ( $row[6] == 'Y' ) {
      $bankBal += $row[3];
    }
    $line .= "<td class=\"$class\" align=\"right\"><img src=\"" .
      ( $row[6] == 'Y' ? 'images/reconciled.png' : 'images/not_reconciled.png' ) .
      "\" alt=\"rec\" /></td>";
    $line .= "<td class=\"$class\" align=\"right\">" .
      ( sprintf ( "%.02f", $bankBal ) ) . "</td>";
    $out[] = $line;
    $lastDate = $row[4];
  }
  dbi_free_result ( $res );
} else {
  fatalError ( translate("Database error") . ": " . dbi_error () );
}

$out[] = "<tr><td colspan=\"7\" style=\"height: 1px; background-color: #000;\"></td></tr>\n";

if ( empty ( $first ) ) {
  $first = count ( $out ) - $NUM_DISPLAY;
}
if ( $first < 0 )
  $first = 0;
for ( $i = $first, $j = 0; $i < count ( $out ) && $j < $NUM_DISPLAY; $i++, $j++ ) {
  print $out[$i];
}


close_table ();

?>

<form action="list.php" method="GET">
<input type="hidden" name="acct" value="<?php echo $acct;?>" />
<input type="hidden" name="first" value="<?php echo ( $first - $NUM_DISPLAY ); ?>" />
<input type="submit" value="Previous 100" />
</form>

<?php
print_trailer ();
?>
