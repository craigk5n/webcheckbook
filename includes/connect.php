<?php

// db settings are in config.php

// Establish a database connection.
$c = dbi_connect ( $db_host, $db_login, $db_password, $db_database );
if ( ! $c ) {
  echo "Error connecting to database:<blockquote>" .
    dbi_error () . "</blockquote>\n";
  exit;
}

?>
