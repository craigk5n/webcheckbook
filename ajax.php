<?php

include_once 'includes/config.php';
include_once 'includes/php-dbi.php';
include_once 'includes/functions.php';
include_once 'includes/connect.php';


/**
 * Sends a JSON error message and terminates the script.
 *
 * @param string $message The error message to send.
 */
function ajax_send_error(string $message): void
{
  header('Content-Type: application/json');
  $response = [
    'error' => 1,
    'message' => $message,
  ];
  echo json_encode($response);
  exit();
}

/**
 * Sends a JSON success response with data and terminates the script.
 *
 * @param array $data The data to send.
 */
function ajax_send_success(array $data): void
{
  header('Content-Type: application/json');
  $response = [
    'error' => 0,
    'message' => 'Success',
    'data' => $data,
  ];
  echo json_encode($response);
  exit();
}

$acct = getIntValue("acct");
if (empty($acct)) {
  ajax_send_error("No account specified");
}

// Get account info
// Using prepared statements is a best practice to prevent SQL injection.
$sql = "SELECT chk_bank, chk_name, chk_account_no, " .
       "chk_balance, chk_bank_balance FROM chk_account WHERE chk_acct_id = ?";
$res = dbi_execute($sql, [$acct]);

$Account = [];
if ($res) {
  $row = dbi_fetch_row($res);
  if ($row) {
    $Account['acct_id'] = $acct;
    $Account['bank'] = $row[0];
    $Account['name'] = $row[1];
    $Account['account_no'] = $row[2];
    $Account['balance'] = $row[3];
    $Account['bank_balance'] = $row[4];
    dbi_free_result($res);
  } else {
    ajax_send_error("No such acct: $acct");
  }
} else {
  ajax_send_error("Error in query:\n" . dbi_error());
}

// Get first and last transaction date.
$sql = 'SELECT MIN(chk_date), MAX(chk_date) FROM chk_trans WHERE chk_acct_id = ?';
$res = dbi_execute($sql, [$acct]);
if ($res) {
  if ($row = dbi_fetch_row($res)) {
    $Account['start_date'] = $row[0];
    $Account['end_date'] = $row[1];
  }
}

$function = getValue('function');

if ($function == 'lastAmount') {
  $desc = getValue('desc');
  $amount = get_last_amount_for_decription($acct, $desc);
  $data = [
    'amount' => $amount,
    'acct' => $acct,
    'description' => $desc
  ];
  ajax_send_success($data);
} else {
  ajax_send_error('No valid function provided.');
}
?>