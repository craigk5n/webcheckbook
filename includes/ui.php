<?php

function print_header ( $title='WebCheckbook' )
{
  print "<html><head><title>" . htmlentities ( $title ) .
   "</title>" .
   '<link rel="stylesheet" type="text/css" href="style.css"/>' .
   "\n";
   include "../style.css";
   //print "<script src=\"includes/jquery-3.3.1.min.js\"></script>\n";
   print "</head><body style=\"margin: 0px;\">\n";
   include "../header.php";
   print '<div style="margin: 25px">' .
     '<table class="sectiontable" cellspacing="0">' .
     '<tr><td class="sectionheader">Checkbook</td></tr>' .
     '<tr><td class="sectioncontent">';
}

function print_trailer ( )
{
  global $acct;
  print "<hr />\n";
  print "<p><b>Go to month:</b>\n";
  $thismonth = date ( "m" );
  $thisyear = date ( "y" );
  for ( $i = 0; $i < 18; $i++ ) {
    $t1 = mktime ( 3, 0, 0, $thismonth, 1, $thisyear );
    $startT = date ( "m/d/Y", $t1 );
    $t2 = mktime ( 3, 0, 0, $thismonth + 1, -1, $thisyear );
    $endT = date ( "m/d/Y", $t2 );
    $thismonth--;
    echo "<a href=\"search.php?acct=$acct&search=&start=$startT&end=$endT\">" .
      date ( "M-Y", $t1 ) . "</a> ";
    if ( $thismonth < 1 ) {
      $thismonth = 12;
      $thisyear--;
    }
  }
  print "<br />\n<a href=\"accounts.php\">" . translate("Accounts") .
    "</a>\n";
  if ( ! empty ( $acct ) )
    print " | <a href=\"list.php?acct=$acct\">List Transactions</a>" .
      " | <a href=\"add_trans.php?acct=$acct\">Add Transactions</a>" .
      " | <a href=\"search.php?acct=$acct\">Search Transactions</a>" .
      " | <a href=\"import.php?acct=$acct\">Import Statement</a>" .
      " | <a href=\"statements.php?acct=$acct\">Statements</a>" .
      " | <a href=\"export.php?acct=$acct\">Export</a>" .
      " | <a href=\"report.php?acct=$acct\">Problem Report</a>";
  print "</p>";
  print '</td></tr></table></div>';
  include "../trailer.php";
  print "</body></html>\n";
}

function print_heading ( $heading )
{
  print "<h2>" . htmlentities ( $heading ) . "</h2>\n";
}

function print_account_info ( $account )
{
  print "<table border=\"0\">\n" .
    "<tr><td>Bank:</td><td>" . htmlentities ( $account['bank'] ) . "</td></tr>\n" .
    "<tr><td>Name:</td><td>" . htmlentities ( $account['name'] ) . "</td></tr>\n" .
    "<tr><td>Account No:</td><td>" . htmlentities ( $account['account_no'] ) . "</td></tr>\n" .
    "<tr><td>Balance:</td><td>" . sprintf ( "%8.2f", $account['balance'] ) . "</td></tr>\n" .
    "<tr><td>Bank Balance:</td><td>" . sprintf ( "%8.2f", $account['bank_balance'] ) . "</td></tr>\n";
  if ( ! empty ( $account['start_date'] ) ) {
    print "<tr><td>Begin Date:</td><td>" . 
      date_to_str ( $account['start_date'], "__mm__/__dd__/__yyyy__", false ) .
      "</td></tr>\n";
  }
  if ( ! empty ( $account['end_date'] ) ) {
    print "<tr><td>End Date:</td><td>" . 
      date_to_str ( $account['end_date'], "__mm__/__dd__/__yyyy__", false ) .
      "</td></tr>\n";
  }
  print "</table>\n";
}

function open_table ( $header )
{
  print "<table><tr>";
  for ( $i = 0; $i < count ( $header ); $i++ ) {
    print "<th class=\"header\">" . htmlentities ( $header[$i] ) . "</th>";
  }
  print "</tr>\n";
}

function print_table_cell ( $cell, $escape=true, $inRed=false )
{
  if ( $inRed )
    print "<td style=\"color:red\"><img src=\"images/warning.png\" alt=\"Warning!\"/>";
  else
    print "<td>";
  if ( $escape )
    print htmlentities ( $cell );
  else
    print $cell;
  print "</td>";
}

function close_table ( )
{
  print "</table>\n";
}

function print_transaction ( $trans, $include_header=1,
  $include_trailer=1, $radio_name='', $radio_value='', $defaultSelected=false, $enabled=true, $wrongAmount=false )
{
  if ( $include_header ) {
    if ( empty ( $radio_name ) )
      $arr =  array ( translate("Date"), translate("Chk#"),
        translate("Amount"), translate("Description"), ' ' );
    else
      $arr =  array ( ' ',  translate("Date"), translate("Chk#"),
        translate("Amount"), translate("Description"), ' ' );
    open_table ( $arr );
  }
  print "<tr>";
  if ( ! empty ( $radio_name ) ) {
    $sel = $defaultSelected ? 'checked="checked"' : '';
    $enable = $enabled ? '' : 'disabled="disabled"';
    print "<td><input name=\"$radio_name\" type=\"radio\" value=\"$radio_value\" $sel $enable></td>";
  }
  $url = "edit_trans.php?acct=" . $trans['account'] . "&trans=" . $trans['trans_id'];
  print_table_cell ( '<a href="' . $url . '">' .
    date_to_str ( $trans['date'], "__mm__/__dd__/__yyyy__", false ) . '</a>', false );
  print_table_cell ( ( empty ( $trans['no'] ) || $trans['no'] == 0 ) ? '-' : $trans['no'] );
  print_table_cell ( sprintf ( "%.2f", $trans['amount'] ), true, $wrongAmount );
  print_table_cell ( $trans['description'] );
  $img = $trans['reconciled'] == 'Y' ? 'images/reconciled.png' : 'images/not_reconciled.png';
  print_table_cell (
    "<img src=\"$img\" alt=\"rec\" />", false );
  print "</tr>\n";
  if ( $include_trailer )
    close_table ();
}

function print_bank_transaction ( $trans, $include_header=1,
  $include_trailer=1 )
{
  if ( $include_header )
    open_table ( array ( translate("Date"), translate("Chk#"),
      translate("Amount"), translate("Description"), translate("Memo"), ' ' ) );
  print "<tr>";
  print_table_cell (
    date_to_str ( $trans['date'], "__mm__/__dd__/__yyyy__", false ) );
  print_table_cell ( empty ( $trans['no'] ) ? '-' : $trans['no'] );
  print_table_cell ( sprintf ( "%.2f", $trans['amount'] ) );
  print_table_cell ( $trans['description'] );
  print_table_cell ( $trans['memo'] );
  $img = empty ( $trans['trans_id'] ) ? 'images/not_reconciled.png' : 'images/reconciled.png';
  print_table_cell (
    "<img src=\"$img\" alt=\"rec\" />", false );
  print "</tr>\n";
  if ( $include_trailer )
    close_table ();
}

?>
