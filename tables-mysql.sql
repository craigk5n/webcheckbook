/*
 * Description:
 *	This file is used to create all tables used by WebCheckbook and
 *	initialize some of those tables with the required data.
 *
 *	The comments in the table definitions will be parsed to
 *	generate a document (in HTML) that describes these tables.
 *
 * History:
 *	11-May-2004	Created
 */

/*
 * Defines an account
 */
CREATE TABLE chk_account (
  /* Unique internal id */
  chk_acct_id INT NOT NULL,
  /* name of financial institution */
  chk_bank VARCHAR(50) NULL,
  /* name/type of account */
  chk_name VARCHAR(25),
  /* account number */
  chk_account_no VARCHAR(25) NULL,
  /* Current balance */
  chk_balance DECIMAL(14,2) NOT NULL DEFAULT 0,
  /* Current bank balance */
  chk_bank_balance DECIMAL(14,2) NOT NULL DEFAULT 0,
  /* Is account archived? (Y/N) */
  chk_is_archived CHAR(1) DEFAULT 'N',
  PRIMARY KEY ( chk_acct_id )
);


/*
 * Defines a transaction
 */
CREATE TABLE chk_trans (
  /* Account */
  chk_acct_id INT NOT NULL,
  /* Unique transaction id */
  chk_trans_id INT NOT NULL,
  /* Type of transaction: */
  /* <ul> */
  /* <li> 1 - deposit </li> */
  /* <li> 2 - debit </li> */
  /* <li> 3 - check </li> */
  /* <li> 4 - service charge/fee </li> */
  /* </ul> */
  chk_type INT NOT NULL,
  /* Check number */
  chk_no INT NULL,
  /* Amount (positive for deposit, negative for all else) */
  chk_amount DECIMAL(14,2) NOT NULL,
  /* Date of transaction (YYYYMMDD) format */
  chk_date INT NOT NULL,
  /* Description */
  chk_description VARCHAR(75) NULL,
  /* Is reconciled? (Y/N) */
  chk_reconciled CHAR(1) DEFAULT 'N',
  PRIMARY KEY ( chk_acct_id, chk_trans_id )
);


/*
 * Define a bank statement (typically for a month period)
 */
CREATE TABLE chk_bank_statement (
  /* Account */
  chk_acct_id INT NOT NULL,
  /* Unique statement id */
  chk_statement_id INT NOT NULL,
  /* Statement description/comment */
  chk_description NULL,
  /* Start date of statement */
  chk_start_date INT NOT NULL,
  /* End date of statement */
  chk_end_date INT NOT NULL,
  PRIMARY KEY ( chk_statement_id )
);

/*
 * Defines a bank statement transaction
 * (typically imported from your bank's website)
 */
CREATE TABLE chk_bank_trans (
  /* Account */
  chk_acct_id INT NOT NULL,
  /* Statement associated with from bank */
  chk_statement_id NOT NULL,
  /* order of transaction within statement */
  chk_sequence INT NOT NULL,
  /* Check number */
  chk_no INT NULL,
  /* Amount (positive for deposit, negative for all else) */
  chk_amount DECIMAL(14,2) NOT NULL,
  /* Date of transaction (YYYYMMDD) format */
  chk_date INT NOT NULL,
  /* Description */
  chk_description VARCHAR(200) NULL,
  /* Memo */
  chk_memo VARCHAR(200) NULL,
  /* corresponding transaction in chk_trans table after reconciled */
  chk_trans_id INT NULL,
  PRIMARY KEY ( chk_statement_id, chk_sequence )
);
