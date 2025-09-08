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
$description = getValue ( "description" );
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

print_header ( "Import Results" );

print_heading ( "Import Results" );


$statement_id = 1;
$res = dbi_query ( "SELECT MAX(chk_statement_id) " .
  "FROM chk_bank_trans" );
if ( $res ) {
  if ( $row = dbi_fetch_row ( $res ) ) {
    $statement_id = $row[0] + 1;
  }
  dbi_free_result ( $res );
}


if ( ! empty ( $_FILES['FileName'] ) )
  $file = $_FILES['FileName'];
else if ( ! empty ( $HTTP_POST_FILES['FileName'] ) )
  $file = $HTTP_POST_FILES['FileName'];

$defaultName = $file['name'];
if ( $description == '' )
  $description = $defaultName;

if ( empty ( $file ) ) {
  fatalError ( "No file uploaded" );
}

if ($file['size'] > 0) {
  $arr = array ();
  if ( ! $fd = @fopen ( $file['tmp_name'], "r" ) ) {
    fatalError ( "Error opening temporary file: " . $file['tmp_name'] );
  }
  $row = 1;
  if (($fd = fopen($file['tmp_name'], "r")) !== FALSE) {
    $data = fgetcsv($fd, 1000, ",");
    # echo "<pre>"; print_r ( $data ); print "</pre>";
    $numHeader = count($data);
    # Example header:
    # Account Number,Post Date,Check,Description,Debit,Credit,Status,Balance
    $dateInd = $chkInd = $descInd = $debitInd = $creditInd = $statusInd = $balanceInd = -1;
    for ( $i = 0; $i < count ( $data ); $i++ ) {
      $h = strtolower($data[$i]);
      if ( preg_match ( '/date/', $h ) ) {
        $dateInd = $i;
      } else if ( preg_match ( '/check/', $h ) ) {
        $chkInd = $i;
      } else if ( preg_match ( '/descr/', $h ) ) {
        $descInd = $i;
      } else if ( preg_match ( '/debit/', $h ) ) {
        $debitInd = $i;
      } else if ( preg_match ( '/credit/', $h ) ) {
        $creditInd = $i;
      } else if ( preg_match ( '/status/', $h ) ) {
        $statusInd = $i;
      } else if ( preg_match ( '/balance/', $h ) ) {
        $balanceInd = $i;
      } else {
      }
    }
    # Some fields are reuired.
    if ( $dateInd < 0 )
      $error .= "Did not find \"date\" in line 1 header.<br>\n";
    if ( $chkInd < 0 )
      $error .= "Did not find \"check\" in line 1 header.<br>\n";
    if ( $descInd < 0 )
      $error .= "Did not find \"description\" in line 1 header.<br>\n";
    if ( $creditInd < 0 )
      $error .= "Did not find \"credit\" in line 1 header.<br>\n";
    if ( $debitInd < 0 )
      $error .= "Did not find \"debit\" in line 1 header.<br>\n";
    if ( $balanceInd < 0 )
      $error .= "Did not find \"balance\" in line 1 header.<br>\n";
    $line = 1;
    if ( strlen ( $error ) == 0 ) {
      while (($data = fgetcsv($fd, 1000, ",")) !== FALSE) {
        $line++;
        $numFields = count($data);
        if ( $numFields != $numHeader ) {
          $error .= "Line $line has $numFields columns instead of $numHeader.<br>\n";
        } else {
          $state = '';
          $date = $amt = $checkNo = $desc = $memo = '';
          $dateU = $data[$dateInd];
          $args = explode ( '/', $dateU );
          $date = sprintf ( "%04d%02d%02d", $args[2], $args[0], $args[1] );
          $credit = $data[$creditInd];
          $debit = $data[$debitInd];
          $checkNo = $data[$chkInd];
          $desc = strtoupper($data[$descInd]);
          if ( $credit > 0.00 ) {
            $amt = $credit;
          } else {
            $amt = 0.0 - $debit;
          }
          if ( ! empty ( $amt ) || ! empty ( $memo ) ) {
            $key = $date . "-" . $line;
            $arr[$key] = array (
              "date" => $date,
              "amt" => $amt,
              "no" => $checkNo,
              "desc" => $desc,
              "memo" => ''
            );
          }

        }
      }
    }
  }
  fclose($fd);

  if ( $error == '' ) {
    $sql = "INSERT INTO chk_bank_statement ( chk_acct_id, chk_statement_id, " .
      "chk_description, chk_start_date, chk_end_date ) VALUES ( $acct, $statement_id, " .
       "'$description', " . date("Ymd") . ', ' . date("Ymd") . " )";
    if ( ! dbi_query ( $sql ) ) {
      fatalError ( "Database error: " . dbi_error () );
    }

    // Now insert transactions in date order.
    $keys = array_keys ( $arr );
    sort ( $keys );
    //echo "<pre>"; print_r ( $arr ); print "</pre>\n";
    for ( $i = 0; $i < count ( $keys ); $i++ ) {
      $k = $keys[$i];
      add_transaction ( $arr[$k]['date'], $arr[$k]['amt'],
        $arr[$k]['no'], $arr[$k]['desc'], $arr[$k]['memo'] );
    }
  }
}

// Update start/end date
if ( empty ( $error ) ) {
  if ( ! empty ( $startDate ) ) {
    $sql = "UPDATE chk_bank_statement SET chk_start_date = $startDate, " .
      "chk_end_date = $endDate " .
      "WHERE chk_acct_id = $acct AND chk_statement_id = $statement_id";
    if ( ! dbi_query ( $sql ) ) {
      echo "Database error: " . dbi_error () . "<br />\n";
    }
  }
}


if ( empty ( $error ) ) {
  print "$sequence transactions were imported\n";
} else {
  print "Errors importing:<br /><br />$error\n";
}

print_trailer ();

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
