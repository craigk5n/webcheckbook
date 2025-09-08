<?php



/**
 * Gets the value resulting from an HTTP POST method.
 * 
 * @param string $name Name used in the HTML form
 *
 * @return string The value used in the HTML form
 *
 * @see getGetValue
 */
function getPostValue ( $name ) {
  global $HTTP_POST_VARS;

  if ( isset ( $_POST ) && is_array ( $_POST ) && ! empty ( $_POST[$name] ) )
    return $_POST[$name];
  if ( ! isset ( $HTTP_POST_VARS ) )
    return null;
  if ( ! isset ( $HTTP_POST_VARS[$name] ) )
    return null;
  return ( $HTTP_POST_VARS[$name] );
}

/**
 * Gets the value resulting from an HTTP GET method.
 *
 * If you need to enforce a specific input format (such as numeric input), then
 * use the {@link getValue()} function.
 *
 * @param string $name Name used in the HTML form or found in the URL
 *
 * @return string The value used in the HTML form (or URL)
 *
 * @see getPostValue
 */
function getGetValue ( $name ) {
  global $HTTP_GET_VARS;

  if ( isset ( $_GET ) && is_array ( $_GET ) && ! empty ( $_GET[$name] ) )
    return $_GET[$name];
  if ( ! isset ( $HTTP_GET_VARS ) )
    return null;
  if ( ! isset ( $HTTP_GET_VARS[$name] ) )
    return null;
  return ( $HTTP_GET_VARS[$name] );
}

/**
 * Gets the value resulting from either HTTP GET method or HTTP POST method.
 *
 * <b>Note:</b> If you need to get an integer value, yuou can use the
 * getIntValue function.
 *
 * @param string $name   Name used in the HTML form or found in the URL
 * @param string $format A regular expression format that the input must match.
 *                       If the input does not match, an empty string is
 *                       returned and a warning is sent to the browser.  If The
 *                       <var>$fatal</var> parameter is true, then execution
 *                       will also stop when the input does not match the
 *                       format.
 * @param bool   $fatal  Is it considered a fatal error requiring execution to
 *                       stop if the value retrieved does not match the format
 *                       regular expression?
 *
 * @return string The value used in the HTML form (or URL)
 *
 * @uses getGetValue
 * @uses getPostValue
 */
function getValue ( $name, $format="", $fatal=false ) {
  $val = getPostValue ( $name );
  if ( ! isset ( $val ) )
    $val = getGetValue ( $name );
  if ( ! isset ( $val  ) )
    return "";
  if ( ! empty ( $format ) && ! preg_match ( "/^" . $format . "$/", $val ) ) {
    // does not match
    if ( $fatal ) {
      die_miserable_death ( "Fatal Error: Invalid data format for $name" );
    }
    // ignore value
    return "";
  }
  return $val;
}

/**
 * Gets an integer value resulting from an HTTP GET or HTTP POST method.
 *
 * @param string $name  Name used in the HTML form or found in the URL
 * @param bool   $fatal Is it considered a fatal error requiring execution to
 *                      stop if the value retrieved does not match the format
 *                      regular expression?
 *
 * @return string The value used in the HTML form (or URL)
 *
 * @uses getValue
 */
function getIntValue ( $name, $fatal=false ) {
  $val = getValue ( $name, "-?[0-9]+", $fatal );
  return $val;
}



// Load default system settings (which can be updated via admin.php)
// System settings are stored in chkl_config.
function load_global_settings () {
  global $login, $readonly;
  global $HTTP_HOST, $SERVER_PORT, $REQUEST_URI, $_SERVER;
  global $SETTINGS;

  $SETTINGS = array ();

  if ( empty ( $HTTP_HOST ) )
    $HTTP_HOST = $_SERVER["HTTP_HOST"];
  if ( empty ( $SERVER_PORT ) )
    $SERVER_PORT = $_SERVER["SERVER_PORT"];
  if ( empty ( $REQUEST_URI ) )
    $REQUEST_URI = $_SERVER["REQUEST_URI"];

  $res = dbi_query ( "SELECT cal_setting, cal_value FROM chk_config" );
  if ( $res ) {
    while ( $row = dbi_fetch_row ( $res ) ) {
      $setting = $row[0];
      $value = $row[1];
      //echo "Setting '$setting' to '$value' <br />\n";
      $SETTINGS[$setting] = $value;

    }
    dbi_free_result ( $res );
  }
}



// send a redirect to the specified page
// MS IIS/PWS has a bug in which it does not allow us to send a cookie
// and a redirect in the same HTTP header.
// See the following for more info on the IIS bug:
//   http://www.faqts.com/knowledge_base/view.phtml/aid/9316/fid/4
function do_redirect ( $url ) {
  global $SERVER_SOFTWARE, $_SERVER, $c;
  if ( empty ( $SERVER_SOFTWARE ) )
    $SERVER_SOFTWARE = $_SERVER["SERVER_SOFTWARE"];
  //echo "SERVER_SOFTWARE = $SERVER_SOFTWARE <br />"; exit;
  if ( substr ( $SERVER_SOFTWARE, 0, 5 ) == "Micro" ) {
    echo "<html><head><title>Redirect</title>" .
      "<meta http-equiv=\"refresh\" content=\"0; url=$url\" /></head><body>" .
      "Redirecting to ... <a href=\"" . $url . "\">here</a>.</body></html>.\n";
  } else {
    Header ( "Location: $url" );
    echo "<html><head><title>Redirect</title></head><body>" .
      "Redirecting to ... <a href=\"" . $url . "\">here</a>.</body></html>.\n";
  }
  dbi_close ( $c );
  exit;
}


// Send header stuff that tells the browser not to cache this page.
function send_no_cache_header () {
  header ( "Expires: Mon, 26 Jul 1997 05:00:00 GMT" );
  header ( "Last-Modified: " . gmdate ( "D, d M Y H:i:s" ) . " GMT" );
  header ( "Cache-Control: no-store, no-cache, must-revalidate" );
  header ( "Cache-Control: post-check=0, pre-check=0", false );
  header ( "Pragma: no-cache" );
}




// Get browser-specified language preference
function get_browser_language () {
  global $HTTP_ACCEPT_LANGUAGE, $browser_languages;
  $ret = "";
  if ( empty ( $HTTP_ACCEPT_LANGUAGE ) )
    $HTTP_ACCEPT_LANGUAGE = $_SERVER["HTTP_ACCEPT_LANGUAGE"] ?? '';
  if ( strlen ( $HTTP_ACCEPT_LANGUAGE ) == 0 )
    return "none";
  $langs = explode ( ",", $HTTP_ACCEPT_LANGUAGE );
  for ( $i = 0; $i < count ( $langs ); $i++ ) {
    $l = strtolower ( trim ( $langs[$i] ) );
    $ret .= "\"$l\" ";
    if ( ! empty ( $browser_languages[$l] ) ) {
      return $browser_languages[$l];
    }
  }
  //if ( strlen ( $HTTP_ACCEPT_LANGUAGE ) )
  //  return "none ($HTTP_ACCEPT_LANGUAGE not supported)";
  //else
    return "none";
}



// Print out a date selection for use in a form.
// params:
//   $prefix - prefix to use in front of form element names
//   $date - currently selected date (in YYYYMMDD) format
function print_date_selection ( $prefix, $date ) {
  print date_selection_html ( $prefix, $date );
}

// Generate a date selection for use in a form and return in.
// params:
//   $prefix - prefix to use in front of form element names
//   $date - currently selected date (in YYYYMMDD) format
function date_selection_html ( $prefix, $date ) {
  $ret = "";
  $num_years = 6;
  if ( strlen ( $date ) != 8 )
    $date = date ( "Ymd" );
  $thisyear = $year = substr ( $date, 0, 4 );
  $thismonth = $month = substr ( $date, 4, 2 );
  $thisday = $day = substr ( $date, 6, 2 );
  if ( $thisyear - date ( "Y" ) >= ( $num_years - 1 ) )
    $num_years = $thisyear - date ( "Y" ) + 2;
  $ret .= "<select name=\"" . $prefix . "day\">";
  for ( $i = 1; $i <= 31; $i++ )
    $ret .= "<option" . ( $i == $thisday ? " selected=\"selected\"" : "" ) . ">$i</option>";
  $ret .= "</select>\n<select name=\"" . $prefix . "month\">";
  for ( $i = 1; $i <= 12; $i++ ) {
    $m = month_short_name ( $i - 1 );
    $ret .= "<option value=\"$i\"" .
      ( $i == $thismonth ? " selected=\"selected\"" : "" ) . ">$m</option>";
  }
  $ret .= "</select>\n<select name=\"" . $prefix . "year\">";
  for ( $i = -1; $i < $num_years; $i++ ) {
    $y = date ( "Y" ) + $i;
    $ret .= "<option value=\"$y\"" .
      ( $y == $thisyear ? " selected=\"selected\"" : "" ) . ">$y</option>";
  }
  $ret .= "</select>\n";
  $ret .= "<input type=\"button\" onclick=\"selectDate('" .
    $prefix . "day','" . $prefix . "month','" . $prefix . "year',$date)\" value=\"" .
    translate("Select") . "...\" />";

  return $ret;
}




// Get the Sunday of the week that the specified date is in.
// (If the date specified is a Sunday, then that date is returned.)
function get_sunday_before ( $year, $month, $day ) {
  $weekday = date ( "w", mktime ( 3, 0, 0, $month, $day, $year ) );
  $newdate = mktime ( 3, 0, 0, $month, $day - $weekday, $year );
  return $newdate;
}

// Get the Monday of the week that the specified date is in.
// (If the date specified is a Monday, then that date is returned.)
function get_monday_before ( $year, $month, $day ) {
  $weekday = date ( "w", mktime ( 3, 0, 0, $month, $day, $year ) );
  if ( $weekday == 0 )
    return mktime ( 3, 0, 0, $month, $day - 6, $year );
  if ( $weekday == 1 )
    return mktime ( 3, 0, 0, $month, $day, $year );
  return mktime ( 3, 0, 0, $month, $day - ( $weekday - 1 ), $year );
}


// Returns week number for specified date
// depending from week numbering settings.
// params:
//   $date - date in UNIX time format
function week_number ( $date ) {
  $tmp = getdate($date);
  $iso    = gregorianToISO($tmp['mday'], $tmp['mon'], $tmp['year']);
  $parts  = explode('-',$iso);
  $week_number = intval($parts[1]);
  return sprintf("%02d",$week_number);
}


// Return the full month name
// params:
//   $m - month (0-11)
function month_name ( $m ) {
  switch ( $m ) {
    case 0: return translate("January");
    case 1: return translate("February");
    case 2: return translate("March");
    case 3: return translate("April");
    case 4: return translate("May_"); // needs to be different than "May"
    case 5: return translate("June");
    case 6: return translate("July");
    case 7: return translate("August");
    case 8: return translate("September");
    case 9: return translate("October");
    case 10: return translate("November");
    case 11: return translate("December");
  }
  return "unknown-month($m)";
}


// Return the abbreviated month name
// params:
//   $m - month (0-11)
function month_short_name ( $m ) {
  switch ( $m ) {
    case 0: return translate("Jan");
    case 1: return translate("Feb");
    case 2: return translate("Mar");
    case 3: return translate("Apr");
    case 4: return translate("May");
    case 5: return translate("Jun");
    case 6: return translate("Jul");
    case 7: return translate("Aug");
    case 8: return translate("Sep");
    case 9: return translate("Oct");
    case 10: return translate("Nov");
    case 11: return translate("Dec");
  }
  return "unknown-month($m)";
}


// Return the full weekday name
// params:
//   $w - weekday (0=Sunday,...,6=Saturday)
function weekday_name ( $w ) {
  switch ( $w ) {
    case 0: return translate("Sunday");
    case 1: return translate("Monday");
    case 2: return translate("Tuesday");
    case 3: return translate("Wednesday");
    case 4: return translate("Thursday");
    case 5: return translate("Friday");
    case 6: return translate("Saturday");
  }
  return "unknown-weekday($w)";
}

// Return the abbreviated weekday name
// params:
//   $w - weekday (0=Sun,...,6=Sat)
function weekday_short_name ( $w ) {
  switch ( $w ) {
    case 0: return translate("Sun");
    case 1: return translate("Mon");
    case 2: return translate("Tue");
    case 3: return translate("Wed");
    case 4: return translate("Thu");
    case 5: return translate("Fri");
    case 6: return translate("Sat");
  }
  return "unknown-weekday($w)";
}

// convert a date from an int format "19991231" into
// "Friday, December 31, 1999", "Friday, 12-31-1999" or whatever format
// the user prefers.
function date_to_str ( $indate, $format="", $show_weekday=true, $short_months=false, $server_time="" ) {
  global $DATE_FORMAT, $TZ_OFFSET;

  if ( strlen ( $indate ) == 0 ) {
    $indate = date ( "Ymd" );
  }

  $newdate = $indate;
  if ( $server_time != "" && $server_time >= 0 ) {
    $y = substr ( $indate, 0, 4 );
    $m = substr ( $indate, 4, 2 );
    $d = substr ( $indate, 6, 2 );
    if ( $server_time + $TZ_OFFSET * 10000 > 240000 ) {
       $newdate = date ( "Ymd", mktime ( 3, 0, 0, $m, $d + 1, $y ) );
    } else if ( $server_time + $TZ_OFFSET * 10000 < 0 ) {
       $newdate = date ( "Ymd", mktime ( 3, 0, 0, $m, $d - 1, $y ) );
    }
  }

  // if they have not set a preference yet...
  if ( $DATE_FORMAT == "" )
    $DATE_FORMAT = "__month__ __dd__, __yyyy__";

  if ( empty ( $format ) )
    $format = $DATE_FORMAT;

  $y = (int) ( $newdate / 10000 );
  $m = (int) ( $newdate / 100 ) % 100;
  $d = $newdate % 100;
  $date = mktime ( 3, 0, 0, $m, $d, $y );
  $wday = strftime ( "%w", $date );

  if ( $short_months ) {
    $weekday = weekday_short_name ( $wday );
    $month = month_short_name ( $m - 1 );
  } else {
    $weekday = weekday_name ( $wday );
    $month = month_name ( $m - 1 );
  }
  $yyyy = $y;
  $yy = sprintf ( "%02d", $y %= 100 );

  $ret = $format;
  $ret = str_replace ( "__yyyy__", $yyyy, $ret );
  $ret = str_replace ( "__yy__", $yy, $ret );
  $ret = str_replace ( "__month__", $month, $ret );
  $ret = str_replace ( "__mon__", $month, $ret );
  $ret = str_replace ( "__dd__", $d, $ret );
  $ret = str_replace ( "__mm__", $m, $ret );

  if ( $show_weekday )
    return "$weekday, $ret";
  else
    return $ret;
}


function fatalError ( $msg )
{
  echo "<html><head><title>Error</title></head>\n" .
    "<body><h2>error</h2>" . $msg . "</body></html>\n";
  exit;
}


function update_balances ( $acct )
{
  $sum = 0;
  $res = dbi_query ( "SELECT SUM(chk_amount) FROM chk_trans " .
    "WHERE chk_acct_id = $acct" );
  if ( $res ) {
    if ( $row = dbi_fetch_row ( $res ) ) {
      $sum = $row[0];
    }
    dbi_free_result ( $res );
    dbi_query ( "UPDATE chk_account SET chk_balance = $sum WHERE " .
      "chk_acct_id = $acct" );
  } else {
    fatalError ( "Database error: " . dbi_error () );
  }
  $sum = 0;
  $res = dbi_query ( "SELECT SUM(chk_amount) FROM chk_trans " .
    "WHERE chk_acct_id = $acct AND chk_reconciled = 'Y'" );
  if ( $res ) {
    if ( $row = dbi_fetch_row ( $res ) ) {
      $sum = $row[0];
    }
    if ( empty ( $sum ) )
      $sum = '0';
    dbi_free_result ( $res );
    dbi_query ( "UPDATE chk_account SET chk_bank_balance = $sum WHERE " .
      "chk_acct_id = $acct" );
  } else {
    fatalError ( "Database error: " . dbi_error () );
  }
}


function shiftDate ( $date, $days )
{
  $year = substr ( $date, 0, 4 );
  $month = substr ( $date, 4, 2 );
  $day = substr ( $date, 6, 2 );
  $time = mktime ( 3, 0, 0, $month, $day + $days, $year );
  return date ( "Ymd", $time );
}


// Find the transaction in our records that is the closest match
// to the transaction from the bank.
function find_transactions ( $bankTrans, $daysBack=14 )
{
  global $acct;
  $ids = array ();
  $date1 = shiftDate ( $bankTrans['date'], 0 - $daysBack );
  $date2 = shiftDate ( $bankTrans['date'], 7 );
  // Look for transactions within 14 days that are for the same amount
  // and that have not been reconciled yet.
  $min = sprintf ( "%.2f", $bankTrans['amount'] - 0.50 );
  $max = sprintf ( "%.2f", $bankTrans['amount'] + 0.50 );
  $foundCheckNum = false;
  $sql = "SELECT chk_trans_id, chk_type, chk_no, chk_amount, chk_date, " .
    "chk_description " .
    "FROM chk_trans " .
    "WHERE chk_amount > $min AND chk_amount < $max " .
    "AND chk_reconciled = 'N' AND " .
    "chk_date >= $date1 AND chk_date <= $date2 " .
    "and chk_acct_id = $acct";
  //echo "SQL: $sql <br />\n";
  $res = dbi_query ( $sql );
  if ( ! $res )
    fatalError ( "Database error: " . dbi_error () );
  $matches = array ();
  while ( $row = dbi_fetch_row ( $res ) ) {
    $match = array (
      "account" => $acct,
      "trans_id" => $row[0],
      "type" => $row[1],
      "no" => $row[2],
      "amount" => $row[3],
      "date" => $row[4],
      "description" => $row[5]
    );
    $matches[] = $match;
    $ids[] = $row[0]; // Add trans id to list to avoid dups
    if ( $match['no'] > 100 && $match['no'] == $bankTrans['no'] )
      $foundCheckNum = true;
  }
  dbi_free_result ( $res );
  if ( ! $foundCheckNum && $bankTrans['no'] > 99 ) {
    // Search for check number...
    $sql = "SELECT chk_trans_id, chk_type, chk_no, chk_amount, chk_date, " .
      "chk_description " .
      "FROM chk_trans " .
      "WHERE chk_reconciled = 'N' AND " .
      "chk_no = " . $bankTrans['no'] . " " .
      "and chk_acct_id = $acct";
    //echo "SQL: $sql <br />\n";
    $res = dbi_query ( $sql );
    if ( ! $res )
      fatalError ( "Database error: " . dbi_error () );
    $matches = array ();
    while ( $row = dbi_fetch_row ( $res ) ) {
      $match = array (
        "account" => $acct,
        "trans_id" => $row[0],
        "type" => $row[1],
        "no" => $row[2],
        "amount" => $row[3],
        "date" => $row[4],
        "description" => $row[5]
      );
      $matches[] = $match;
      $ids[] = $row[0]; // Add trans id to list to avoid dups
    }
  }
  // Also check for a sign mistake in case they called a deposit an expense by mistake.
  $min = sprintf ( "%.2f", 0.0 - $bankTrans['amount'] - 0.50 );
  $max = sprintf ( "%.2f", 0.0 - $bankTrans['amount'] + 0.50 );
  $sql = "SELECT chk_trans_id, chk_type, chk_no, chk_amount, chk_date, " .
    "chk_description " .
    "FROM chk_trans " .
    "WHERE chk_amount > $min AND chk_amount < $max " .
    "AND chk_reconciled = 'N' AND " .
    "chk_date >= $date1 AND chk_date <= $date2 " .
    "and chk_acct_id = $acct";
  //echo "SQL: $sql <br />\n";
  $res = dbi_query ( $sql );
  if ( ! $res )
    fatalError ( "Database error: " . dbi_error () );
  while ( $row = dbi_fetch_row ( $res ) ) {
    $match = array (
      "account" => $acct,
      "trans_id" => $row[0],
      "type" => $row[1],
      "no" => $row[2],
      "amount" => $row[3],
      "date" => $row[4],
      "description" => $row[5]
    );
    $matches[] = $match;
    $ids[] = $row[0]; // Add trans id to list to avoid dups
  }

  // Also include the most recent unreconciled transactions that
  // are on or before the post date and within 30 days and
  // not yet reconciled... with a limit of 10.
  $date1 = shiftDate ( $bankTrans['date'], 0 - 30 );
  $date2 = shiftDate ( $bankTrans['date'], 0 );
  $sql = "SELECT chk_trans_id, chk_type, chk_no, chk_amount, chk_date, " .
    "chk_description " .
    "FROM chk_trans " .
    "WHERE chk_reconciled = 'N' AND " .
    "chk_date >= $date1 AND chk_date <= $date2 " .
    "AND chk_acct_id = $acct " .
    "ORDER BY chk_date DESC LIMIT 10";
  //echo "SQL: $sql <br />\n";
  $res = dbi_query ( $sql );
  if ( ! $res )
    fatalError ( "Database error: " . dbi_error () );
  while ( $row = dbi_fetch_row ( $res ) ) {
    if ( ! in_array ( $row[0], $ids ) ) {
      $match = array (
        "account" => $acct,
        "trans_id" => $row[0],
        "type" => $row[1],
        "no" => $row[2],
        "amount" => $row[3],
        "date" => $row[4],
        "description" => $row[5]
      );
      $matches[] = $match;
      $ids[] = $row[0]; // Add trans id to list to avoid dups
    }
  }

  return $matches;
}

function get_description_from_prior_reconcile ( $acct, $bankDescription ) {
   $ret = '';
   $sql = 'SELECT chk_trans.chk_description ' .
     'FROM chk_bank_trans, chk_trans ' .
     'WHERE chk_trans.chk_trans_id = chk_bank_trans.chk_trans_id ' .
     'AND chk_bank_trans.chk_description = ? ' .
     'AND chk_trans.chk_acct_id = ? '.
     'ORDER BY chk_trans.chk_date DESC LIMIT 1';
   $params = [$bankDescription, $acct];
   $res = dbi_execute ( $sql, $params );
   $descriptions = array ();
   if ( $row = dbi_fetch_row ( $res ) ) {
     $ret = $row[0];
   }
   dbi_free_result ( $res );
   return $ret;
}

function get_last_amount_for_decription ( $acct, $description ) {
  $ret = '';
  $sql = 'SELECT chk_amount FROM chk_trans ' .
    'WHERE chk_acct_id = ? AND ' .
    'chk_description = ? ' .
    'ORDER BY chk_date DESC LIMIT 1';
  $params = [$acct,$description];
  $res = dbi_execute ( $sql, $params );
  $descriptions = array ();
  if ( $row = dbi_fetch_row ( $res ) ) {
    $ret = $row[0];
  }
  dbi_free_result ( $res );
  return $ret;
}


?>
