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

if ( ! empty ( $start ) ) {
  $dateAr = preg_split ( "/[\/\-]/", $start );
  if ( empty ( $dateAr[2] ) )
    $dateAr[2] = date ( "Y" );
  else {
    if ( $dateAr[2] < 100 )
      $dateAr[2] += 2000;
    if ( $dateAr[2] > 2050 )
      $dateAr[2] -= 100; // 1990s
  }
  $start = sprintf ( "%04d%02d%02d", $dateAr[2], $dateAr[0], $dateAr[1] );
  $startPretty = sprintf ( "%d/%d/%d", $dateAr[0], $dateAr[1], $dateAr[2] );
}

if ( ! empty ( $end ) ) {
  $dateAr = preg_split ( "/[\/\-]/", $end );
  if ( empty ( $dateAr[2] ) )
    $dateAr[2] = date ( "Y" );
  else {
    if ( $dateAr[2] < 100 )
      $dateAr[2] += 2000;
    if ( $dateAr[2] > 2050 )
      $dateAr[2] -= 100; // 1990s
  }
  $end = sprintf ( "%04d%02d%02d", $dateAr[2], $dateAr[0], $dateAr[1] );
  $endPretty = sprintf ( "%d/%d/%d", $dateAr[0], $dateAr[1], $dateAr[2] );
}

?>
<form action="search.php">
<input type="hidden" name="acct" value="<?php echo $acct; ?>" />
<table border="0">
<tr><td><b>Description:</b></td><td><input name="search" value="<?php echo htmlentities ( $search );?>" /></td></tr>
<tr><td><b>Date Range:</b></td><td>
  <input name="start" size="11" value="<?php echo htmlentities ( $startPretty );?>" />
  <input name="end" size="11" value="<?php echo htmlentities ( $endPretty );?>" />
</td></tr>
<tr><td><b>Reconciled:</b></td>
<td>
  <input type="radio" id="NotReconciled" name="reconciled" value="no"
   <?php if ( ! empty($rec) && $rec == 'no' ) echo 'checked="checked"';?> >
    <label for="NotReconciled">No</label>
  <input type="radio" id="Reconciled" name="reconciled" value="yes"
   <?php if ( ! empty($rec) && $rec == 'yes' ) echo 'checked="checked"';?> >
    <label for="Reconciled">Yes</label>
  <input type="radio" id="Either" name="reconciled" value="either"
   <?php if ( ! empty($rec) && $rec == 'either' ) echo 'checked="checked"';?> >
    <label for="Either">Either</label>
</td></tr>
<tr><td colspan="2"><input type="submit" value="Search" /></td></tr>
</table>
</form>
<?php


if ( ! empty ( $search ) || ! empty ( $start ) || ! empty ( $end ) ) {
  $sql = "SELECT chk_trans_id, chk_type, chk_no, chk_amount, " .
    "chk_date, chk_description, chk_reconciled " .
    "FROM chk_trans " .
    "WHERE chk_acct_id = $acct ";
  if ( ! empty ( $search ) )
    $sql .= "AND chk_description LIKE ( '%" . $search . "%' ) ";
  if ( ! empty ( $start ) )
    $sql .= "AND chk_date >= $start ";
  if ( ! empty ( $end ) )
    $sql .= "AND chk_date <= $end ";
  if ( ! empty ( $rec ) && $rec == 'yes' )
    $sql .= "AND chk_reconciled = 'Y' ";
  if ( ! empty ( $rec ) && $rec == 'no' )
    $sql .= "AND NOT chk_reconciled = 'Y' ";
  $sql .= "ORDER BY chk_date, chk_type, chk_no ASC";
  //echo "SQL: $sql <p>";
  $res = dbi_query ( $sql );
  open_table ( array ( "Date", "Chk#", "Comment", "Amount", "Total", ' ' ) );
  $bal = 0.0;
  $bankBal = 0.0;
  if ( $res ) {
    $out = array ();
    $cnt = 0;
    $lastDate = '';
    while ( $row = dbi_fetch_row ( $res ) ) {
      $cnt++;
      if ( $cnt == 0 || $row[4] != $lastDate ) {
        $out[] = "<tr><td colspan=\"6\" style=\"height: 1px; background-color: #000;\"></td></tr>\n";
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
      $num = empty ( $row[2] ) ? '-' : $row[2];
      $line .= "<td class=\"$class\">" . $num . "</td>";
      $line .= "<td class=\"$class\">" . htmlentities ( $row[5] ) . "</td>";
      $line .= "<td class=\"$class\" align=\"right\">" . ( sprintf ( "%.02f", $row[3] ) ) . "</td>";
      $bal += $row[3];
      $line .= "<td class=\"$class\" align=\"right\">" . ( sprintf ( "%.02f", $bal ) ) . "</td>";
      $line .= "<td class=\"$class\" align=\"right\"><img src=\"" .
        ( $row[6] == 'Y' ? 'images/reconciled.png' : 'images/not_reconciled.png' ) .
        "\" alt=\"rec\" /></td>";
      $out[] = $line;
      $lastDate = $row[4];
    }
    dbi_free_result ( $res );
  } else {
    fatalError ( translate("Database error") . ": " . dbi_error () );
  }

  $out[] = "<tr><td colspan=\"6\" style=\"height: 1px; background-color: #000;\"></td></tr>\n";

  for ( $i = 0; $i < count ( $out ) && $i < 500; $i++ ) {
    print $out[$i];
  }
  close_table ();
}

?>

<?php
print_trailer ();
?>
