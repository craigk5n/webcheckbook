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
if ( empty ( $num ) )
  $num = 15;

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

// Get MAX check number.
$res = dbi_execute ( 'SELECT MAX(chk_no) FROM chk_trans ' .
  'WHERE chk_acct_id = ?', [ $acct ] );
if ( ! $res )
  fatalError ( "Error in query:<br />$sql<br />" . dbi_error () );
$maxCheck = "";
if ( ( $row = dbi_fetch_row ( $res ) ) && $row[0] > 99 ) {
  $maxCheck = $row[0];
}
dbi_free_result ( $res );

print_header ( translate("Account") . ": " . $Account['name'] );

print_heading ( translate("Account") . ": " . $Account['name'] );

print_account_info ( $Account );

?>


<p><b>Last check number:</b> <?php echo $maxCheck;?> </p>

<form action="add_trans_handler.php" method="POST">
<input type="hidden" name="acct" value="<?php echo $acct; ?>" />

<table border="0" class="add_transactions_table" id="add_transactions_table">
<tr><th>Date</th><th>Type</th><th>ChkNo</th><th>Description</th><th>Amount</th></tr>
<?php
for ( $i = 0; $i < $num; $i++ ) {
  echo "<tr><td><input size=\"11\" class=\"date\" id=\"date_$i\" name=\"date_$i\"  ";
  if ( $i == 0 )
    echo "value=\"" . date ( "m/d/Y" ) . "\" ";
  echo "onfocus=\"steal_date(this.form,$i,$num)\" ";
  echo "onBlur=\"this.value = clean_date(this.value)\" ";
  echo "/></td>\n";
  echo "<td><select name=\"type_$i\"><option value=\"2\">Debit<option value=\"3\">Check<option value=\"4\">Charge/Fee<option value=\"1\">Deposit</select></td>";
  echo "<td><input size=\"7\" id=\"num_$i\" name=\"num_$i\" ";
  echo "onfocus=\"suggest_check_number(this.form,$i,$num)\" ";
  echo "/></td>\n";
  echo "<td>";
  echo '<div class="autocomplete">';
  echo "<input class=\"autocomplete\" autocomplete=\"off\" size=\"40\" id=\"description_$i\" name=\"description_$i\" " .
    "onBlur=\"this.value = this.value.toUpperCase();\" placeholder=\"Description\" /></div></td>\n";
  echo "<td><input size=\"8\" name=\"amount_$i\" onFocus=\"onFocusAmount(this.form,$i);\" /></td>\n";
  echo "</tr>\n";
}
?>
</table>

<input type="submit" value="Add" />
</form>

<script language="JavaScript">

var lastCheck = "<?php echo $maxCheck;?>";

// Copy the date down from the row above if this input field is empty.
function steal_date ( form, num, total )
{
  var str, j;

  if ( num > 0 ) {
    var ob = form.elements["date_" + num];
    if ( ob.value == "" ) {
      var prev = form.elements["date_" + (num-1)];
      if ( prev.value != "" ) {
        ob.value = prev.value;
        ob.select ();
      }
    }
  }
}

// Clean up a partial date.
// "1" will be the 1st of the current month, current year.
// 11/1 will be Nov 1 of current year unless that';s in the future,
// then it will be Nov 1 of last year.
function clean_date ( dateIn )
{
  var args = dateIn.split('/');
  var month = parseInt(args[0]);
  var day = parseInt(args[1]);
  var year = new Date().getFullYear();
  var m = ( year - ( year % 100 ) ) / 100;
  if ( args.length == 1 ) {
    month = new Date().getMonth() + 1;
    day = args[0];
  }
  if ( args.length > 2 )
    year = parseInt(args[2]);
  else {
    var thisMonth = new Date().getMonth() + 1;
    if ( month > thisMonth )
      year--;
  }
  if ( year < 100 )
    year += ( m * 100 );
  console.log ( 'args0=' + args[0] + ", args1=" + args[1] +
    "args2=" + args[2] + ", month=" + month + ", day=" + day +
    ". year=" + year );
  var ret = "" + month + "/" + day + "/" + year;
  return ret;
}

function suggest_check_number ( form, num, total )
{
  var str, j;

  // Is this a check?
  var sel = form.elements["type_" + num];
  var selValue = sel.options[sel.selectedIndex].value;
  console.log ('selValue=' + selValue);
  if ( selValue != '3' ) {
    // Move focus to description
    var desc = form.elements["description_" + num];
    //sel.focus ();
    return;
  }

  if ( selValue == '3' ) {
    var ob = form.elements["num_" + num];
    if ( ob.value == "" ) {
      var found = false;
      var anum = num;
      while ( ! found && anum > 0 ) {
        var prev = form.elements["num_" + (anum-1)];
        if ( prev.value != "" ) {
          ob.value = parseInt(prev.value) + 1;
          ob.select ();
          found = true;
        }
        anum--;
      }
      if ( ! found ) {
<?php if ( $maxCheck != 'None' ) { ?>
        ob.value = '<?php echo ( $maxCheck + 1 );?>';
        ob.select ();
<?php } ?>
      }
    }
  }
}

function onFocusAmount(form, num) {
    console.log("onFocusAmount with num=" + num);
    //closeAutomcomplete();
    if (num >= 0) {
        var ob = form.elements["description_" + num];
        var desc = ob.value;
        var amountOb = form.elements["amount_" + num];
        if (amountOb.value != '') {
            console.log('Amount exists....');
        } else if (desc != '') {
            console.log('description = ' + desc);
            var xmlhttp = new XMLHttpRequest();
            xmlhttp.onreadystatechange = function() {
                if (this.readyState == 4 && this.status == 200) {
                    console.log('AJAX response: ' + this.responseText);
                    var myObj = JSON.parse(this.responseText);
                    if (myObj.error !== 0) {
                        alert("AJAX error: " + myObj.message);
                    } else {
                        // Correctly access the amount from the data object
                        var amt = Math.abs(myObj.data.amount);
                        if (amt > 0.1) {
                            amountOb.value = amt;
                            amountOb.select();
                        }
                    }
                }
            };
            var url = "ajax.php?acct=<?php echo $acct;?>&function=lastAmount&desc=" +
                encodeURIComponent(desc);
            console.log('URL=' + url);
            xmlhttp.open("GET", url, true);
            xmlhttp.send();
        }
    }
}


// The commented out jQuery block is not relevant to the autocomplete issue.
// If you plan to use jQuery, ensure it's properly included and the code is uncommented.

function autocomplete(inp, arr) {
  /*the autocomplete function takes two arguments,
  the text field element and an array of possible autocompleted values:*/
  var currentFocus;

  /*execute a function when someone writes in the text field:*/
  inp.addEventListener("input", function(e) {
      var a, b, i, val = this.value;
      /*close any already open lists of autocompleted values*/
      closeAllLists();
      if (!val) { return false;}
      currentFocus = -1;
      /*create a DIV element that will contain the items (values):*/
      a = document.createElement("DIV");
      a.setAttribute("id", this.id + "autocomplete-list");
      a.setAttribute("class", "autocomplete_items");
      /*append the DIV element as a child of the autocomplete container:*/
      this.parentNode.appendChild(a);
      /*for each item in the array...*/
      for (i = 0; i < arr.length; i++) {
        /*check if the item starts with the same letters as the text field value:*/
        // *** FIX START ***
        // Changed arr['i'] to arr[i] to correctly access array elements.
        if (arr[i].substr(0, val.length).toUpperCase() == val.toUpperCase()) {
        // *** FIX END ***
          /*create a DIV element for each matching element:*/
          b = document.createElement("DIV");
          b.setAttribute("class", "autocomplete_div");
          /*make the matching letters bold:*/
          b.innerHTML = "<strong>" + arr[i].substr(0, val.length) + "</strong>"; // Also corrected here
          b.innerHTML += arr[i].substr(val.length); // And here
          /*insert a input field that will hold the current array item's value:*/
          b.innerHTML += "<input type='hidden' value='" + arr[i] + "'>";
          /*execute a function when someone clicks on the item value (DIV element):*/
          b.addEventListener("click", function(e) {
              /*insert the value for the autocomplete text field:*/
              inp.value = this.getElementsByTagName("input")[0].value;
              /*close the list of autocompleted values,
              (or any other open lists of autocompleted values:*/
              closeAllLists();
              inp.focus();
          });
          a.appendChild(b);
        }
      }
  });
  /*execute a function presses a key on the keyboard:*/
  inp.addEventListener("keydown", function(e) {
      var x = document.getElementById(this.id + "autocomplete-list");
      if (x) x = x.getElementsByTagName("div");
      if (e.keyCode == 40) {
        /*If the arrow DOWN key is pressed,
        increase the currentFocus variable:*/
        currentFocus++;
        /*and and make the current item more visible:*/
        addActive(x);
      } else if (e.keyCode == 38) { //up
        /*If the arrow UP key is pressed,
        decrease the currentFocus variable:*/
        currentFocus--;
        /*and and make the current item more visible:*/
        addActive(x);
      } else if (e.keyCode == 13) {
        /*If the ENTER key is pressed, prevent the form from being submitted,*/
        e.preventDefault();
        if (currentFocus > -1) {
          /*and simulate a click on the "active" item:*/
          if (x) x[currentFocus].click(); // Corrected index access
        }
      }
  });
  function addActive(x) {
    /*a function to classify an item as "active":*/
    if (!x) return false;
    /*start by removing the "active" class on all items:*/
    removeActive(x);
    if (currentFocus >= x.length) currentFocus = 0;
    if (currentFocus < 0) currentFocus = (x.length - 1);
    /*add class "autocomplete-active":*/
    x[currentFocus].classList.add("autocomplete-active"); // Corrected index access
  }
  function removeActive(x) {
    /*a function to remove the "active" class from all autocomplete items:*/
    for (var i = 0; i < x.length; i++) {
      // *** Potential Fix ***
      // Original: x['i'].classList.remove("autocomplete-active");
      // Corrected to: x[i].classList.remove("autocomplete-active");
      // Assuming x is an array-like object (e.g., from getElementsByTagName)
      x[i].classList.remove("autocomplete-active");
    }
  }
  function closeAllLists(elmnt) {
    /*close all autocomplete lists in the document,
    except the one passed as an argument:*/
    var x = document.getElementsByClassName("autocomplete_items");
    for (var i = 0; i < x.length; i++) {
      // *** Potential Fix ***
      // Original: if (elmnt != x['i'] && elmnt != inp) {
      // Corrected to: if (elmnt != x[i] && elmnt != inp) {
      // Assuming x is an array-like object
      if (elmnt != x[i] && elmnt != inp) {
        // *** Potential Fix ***
        // Original: x['i'].parentNode.removeChild(x['i']);
        // Corrected to: x[i].parentNode.removeChild(x[i]);
        // Assuming x is an array-like object
        x[i].parentNode.removeChild(x[i]);
      }
    }
  }
  /*execute a function when someone clicks in the document:*/
  document.addEventListener("click", function (e) {
      closeAllLists(e.target);
      });
}

function closeAutomcomplete() {
  /*close all autocomplete lists in the document,
  except the one passed as an argument:*/
  var x = document.getElementsByClassName("autocomplete_items");
  for (var i = 0; i < x.length; i++) {
    // *** Potential Fix ***
    // Original: x['i'].parentNode.removeChild(x['i']);
    // Corrected to: x[i].parentNode.removeChild(x[i]);
    // Assuming x is an array-like object
    x[i].parentNode.removeChild(x[i]);
  }
}


// Create an array of the most commonly used descriptions ordered
// by most used with no more than 1000.
var names = [
<?php
$sql = 'select count(chk_description), chk_description from chk_trans ' .
  'WHERE chk_acct_id = ? ' .
  'GROUP BY chk_description ' .
  'ORDER BY count(chk_description) DESC LIMIT 1000';
$res = dbi_execute ( $sql, [ $acct ] );
if ( $res ) {
  $i = 0;
  while ( $row = dbi_fetch_row ( $res ) ) {
    if ( $i++ > 0 ) echo ",\n";
    echo "  \"" . $row[1] . "\"";
  }
  dbi_free_result ( $res );
}
?>
  ];


<?php for ( $i = 0; $i < $num; $i++ ) { ?>
autocomplete(document.getElementById("description_<?php echo $i;?>"), names);
<?php } ?>
</script>

<?php
print_trailer ();
?>