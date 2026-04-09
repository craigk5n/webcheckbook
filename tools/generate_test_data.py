#!/usr/bin/env python3
"""
Generate synthetic test data for WebCheckbook.

Produces:
  - tools/output/seed-data.sql            SQL to TRUNCATE and populate
                                          chk_account and chk_trans for 18
                                          months of activity
  - tools/output/checking-YYYY-MM.csv     One "statement" CSV per period
                                          for the checking account
  - tools/output/savings-YYYY-MM.csv      One "statement" CSV per period
                                          for the savings account

Statements are split by calendar month by default. Pass --period quarter
to emit one file per calendar quarter instead (e.g., savings-2045-Q4.csv).
The running Balance column is carried across statements the way a real
bank statement does — each period's opening balance equals the prior
period's closing balance.

The SQL file represents what the household has entered into WebCheckbook.
The CSV files represent what the bank later reports, with realistic timing
drift (2-4 days typical, occasionally ~15) and a few bank-only and
user-only transactions so the reconcile workflow has something interesting
to show.

Household profile:
  - ~$80k gross / ~$2,000 biweekly net paycheck, direct deposited
  - Single checking + single savings account
  - Fixed monthly bills (mortgage, utilities, insurance, subscriptions)
  - Weekly variable spend (groceries, gas, restaurants)
  - Paper checks reserved for household services (HVAC, irrigation,
    mobile dog grooming, handyman, etc.)
  - Monthly $500 auto-transfer checking -> savings
  - Older months are fully reconciled; the most recent month is not,
    so reconcile.php has work to do

Usage:
    python3 tools/generate_test_data.py [--seed N] [--out DIR] \
                                        [--period month|quarter]
    mysql -u <user> -p checkbook < tools/output/seed-data.sql
    # Then import each statement CSV via import.php in the browser,
    # one statement at a time, to mirror the real reconcile workflow.

Standard library only. No external dependencies.
"""
from __future__ import annotations

import argparse
import csv
import os
import random
from dataclasses import dataclass, field
from datetime import date, timedelta
from typing import List, Optional

# ---------------------------------------------------------------------------
# Configuration
# ---------------------------------------------------------------------------

MONTHS_OF_HISTORY = 18
CHECKING_ACCT_ID = 1
SAVINGS_ACCT_ID = 2
CHECKING_OPENING_BALANCE = 5_000.00
SAVINGS_OPENING_BALANCE = 8_400.00
BIWEEKLY_NET_PAYCHECK = 2_400.00
MONTHLY_AUTO_TRANSFER = 500.00
FIRST_CHECK_NO = 1001

# "Today" for the generator. Default is the real today.
TODAY = date.today()
# Last ~30 days of activity are intentionally unreconciled.
UNRECONCILED_CUTOFF = TODAY - timedelta(days=30)

# Transaction types (mirror chk_trans.chk_type)
TYPE_DEPOSIT = 1
TYPE_DEBIT = 2
TYPE_CHECK = 3
TYPE_FEE = 4


# ---------------------------------------------------------------------------
# Fictional payees
# ---------------------------------------------------------------------------

FIXED_BILLS = [
    # (description, amount, day_of_month)
    ("XYZ MORTGAGE CO",         1_650.00,  1),
    ("ACME POWER COMPANY",        135.00,  7),
    ("BLUEFLAME NATURAL GAS",      62.00,  9),
    ("CLEARWATER MUNICIPAL WATER", 48.00, 11),
    ("FIBERLINK INTERNET",         79.00, 12),
    ("RINGWAVE WIRELESS",         115.00, 14),
    ("SHIELDSURE AUTO INS",       142.00, 16),
    ("WELLPATH HEALTH INS",       285.00, 18),
    ("NORTHSTAR LIFE INS",         48.00, 20),
    ("GREENLAWN HOA",              75.00, 22),
]

STREAMING_SUBS = [
    ("STREAMFLIX MONTHLY",   17.99,  3),
    ("TUNEWAVE MUSIC",       10.99,  5),
    ("CLOUDBOX STORAGE",      2.99,  6),
    ("NEWSBYTE DIGITAL",      9.99, 15),
    ("SWEATSPACE GYM",       39.00, 25),
]

GROCERY_STORES = [
    ("FRESHMART GROCERY",        (95.00, 190.00)),
    ("GREENFIELD SUPERMARKET",   (70.00, 160.00)),
    ("VALUEBASKET WAREHOUSE",   (120.00, 240.00)),
]

GAS_STATIONS = [
    ("PETROMAX FUEL #142",       (35.00,  65.00)),
    ("QUIKFILL GAS #7",          (30.00,  60.00)),
    ("HIGHWAY STOP #22",         (32.00,  58.00)),
]

RESTAURANTS = [
    ("BELLA ROSA ITALIAN",       (28.00,  72.00)),
    ("TACO JUNCTION",            (14.00,  38.00)),
    ("PINE & OAK BISTRO",        (45.00, 110.00)),
    ("NOODLE HOUSE EXPRESS",     (16.00,  34.00)),
    ("SUNRISE DINER",            (18.00,  42.00)),
]

ONLINE_SHOPPING = [
    ("SDF ONLINE BOOKS",         (12.00,  55.00)),
    ("QUICKSHIP MARKETPLACE",    (18.00, 140.00)),
    ("HEARTHGOODS HOME",         (22.00,  85.00)),
    ("WIREWORKS ELECTRONICS",    (40.00, 260.00)),
]

OCCASIONAL_EXPENSES = [
    ("RIVERBEND MEDICAL COPAY",  (25.00,  60.00)),
    ("BRIGHTSMILE DENTAL",       (85.00, 220.00)),
    ("PETPALS VETERINARY",       (55.00, 175.00)),
    ("CITY PARKING AUTHORITY",   ( 8.00,  18.00)),
    ("LENSCRAFT VISION",         (95.00, 210.00)),
]

# Things the user writes paper checks for
CHECK_PAYEES = [
    ("COMFORTAIR HVAC REPAIR",   (185.00, 620.00)),
    ("GREENJET IRRIGATION STARTUP", (95.00, 145.00)),
    ("PAWS ON WHEELS MOBILE GROOMING", (75.00, 110.00)),
    ("HANDY HANK HOME REPAIR",   (120.00, 480.00)),
    ("TREELINE ARBORIST",        (275.00, 650.00)),
    ("SPARKLEPRO CHIMNEY SWEEP", ( 95.00, 185.00)),
    ("DEEPCLEAN CARPET SVC",     (140.00, 280.00)),
    ("MEADOWLAWN LANDSCAPING",   ( 80.00, 200.00)),
    ("KIDDOS SOCCER CLUB DUES",  ( 85.00, 165.00)),
    ("ST MARKS CHURCH DONATION", ( 50.00, 150.00)),
]

BANK_ONLY_FEES = [
    ("MONTHLY MAINTENANCE FEE",  12.00),
    ("PAPER STATEMENT FEE",       3.00),
    ("FOREIGN TRANSACTION FEE",   4.25),
]


# ---------------------------------------------------------------------------
# Data model
# ---------------------------------------------------------------------------


@dataclass
class Transaction:
    acct_id: int
    trans_id: int
    ttype: int
    amount: float  # signed: positive=deposit, negative=everything else
    when: date
    description: str
    check_no: Optional[int] = None
    reconciled: bool = False
    # For bank CSV generation:
    appears_in_bank: bool = True
    bank_date_offset_days: int = 0  # days later than `when`


@dataclass
class Account:
    acct_id: int
    bank: str
    name: str
    account_no: str
    opening_balance: float
    transactions: List[Transaction] = field(default_factory=list)


# ---------------------------------------------------------------------------
# Helpers
# ---------------------------------------------------------------------------


def months_ago(n: int, reference: date = TODAY) -> date:
    """Return the first day of the month that is `n` months before `reference`."""
    year = reference.year
    month = reference.month - n
    while month <= 0:
        month += 12
        year -= 1
    return date(year, month, 1)


def day_in_month(year: int, month: int, day: int) -> date:
    """Clamp `day` to the last valid day of the given month."""
    if month == 12:
        next_first = date(year + 1, 1, 1)
    else:
        next_first = date(year, month + 1, 1)
    last_day = (next_first - timedelta(days=1)).day
    return date(year, month, min(day, last_day))


def next_trans_id(counter: List[int]) -> int:
    counter[0] += 1
    return counter[0]


def sql_escape(s: str) -> str:
    return s.replace("\\", "\\\\").replace("'", "''")


def bank_offset(rng: random.Random) -> int:
    """Typical posting delay: 2-4 days, occasional 15."""
    if rng.random() < 0.05:
        return rng.randint(14, 16)
    return rng.randint(2, 4)


# ---------------------------------------------------------------------------
# Generators
# ---------------------------------------------------------------------------


def generate_checking(rng: random.Random) -> Account:
    acct = Account(
        acct_id=CHECKING_ACCT_ID,
        bank="First Meridian Bank",
        name="Household Checking",
        account_no="****4821",
        opening_balance=CHECKING_OPENING_BALANCE,
    )
    tid = [0]
    start = months_ago(MONTHS_OF_HISTORY - 1)  # first day of earliest month
    # walk day-by-day through the window
    end = TODAY

    # --- Biweekly paychecks (every other Friday) ---
    # Anchor on the first Friday on or after `start`.
    d = start
    while d.weekday() != 4:  # Friday
        d += timedelta(days=1)
    while d <= end:
        acct.transactions.append(Transaction(
            acct_id=acct.acct_id,
            trans_id=next_trans_id(tid),
            ttype=TYPE_DEPOSIT,
            amount=BIWEEKLY_NET_PAYCHECK + rng.choice([-12.44, 0, 0, 0, 8.10, 15.22]),
            when=d,
            description="PAYROLL DIRECT DEPOSIT - NORTHWIND LOGISTICS",
            bank_date_offset_days=0,  # direct deposit posts same day
        ))
        d += timedelta(days=14)

    # --- Monthly auto-transfer to savings ---
    # Recorded as a debit here; the matching credit goes into savings generator
    months = []
    y, m = start.year, start.month
    while (y, m) <= (end.year, end.month):
        months.append((y, m))
        m += 1
        if m > 12:
            m = 1
            y += 1

    for y, m in months:
        when = day_in_month(y, m, 5)
        if when > end:
            continue
        acct.transactions.append(Transaction(
            acct_id=acct.acct_id,
            trans_id=next_trans_id(tid),
            ttype=TYPE_DEBIT,
            amount=-MONTHLY_AUTO_TRANSFER,
            when=when,
            description="TRANSFER TO SAVINGS ****9032",
            bank_date_offset_days=0,
        ))

    # --- Fixed monthly bills ---
    for y, m in months:
        for desc, amt, day in FIXED_BILLS:
            when = day_in_month(y, m, day)
            if when > end:
                continue
            acct.transactions.append(Transaction(
                acct_id=acct.acct_id,
                trans_id=next_trans_id(tid),
                ttype=TYPE_DEBIT,
                amount=-amt,
                when=when,
                description=desc,
                bank_date_offset_days=bank_offset(rng),
            ))
        for desc, amt, day in STREAMING_SUBS:
            when = day_in_month(y, m, day)
            if when > end:
                continue
            acct.transactions.append(Transaction(
                acct_id=acct.acct_id,
                trans_id=next_trans_id(tid),
                ttype=TYPE_DEBIT,
                amount=-amt,
                when=when,
                description=desc,
                bank_date_offset_days=bank_offset(rng),
            ))

    # --- Weekly variable spend: groceries, gas, restaurants ---
    d = start
    while d <= end:
        # Groceries 1x/week (Sat)
        if d.weekday() == 5:
            store, (lo, hi) = rng.choice(GROCERY_STORES)
            acct.transactions.append(Transaction(
                acct_id=acct.acct_id,
                trans_id=next_trans_id(tid),
                ttype=TYPE_DEBIT,
                amount=-round(rng.uniform(lo, hi), 2),
                when=d,
                description=store,
                bank_date_offset_days=bank_offset(rng),
            ))
        # Gas 1x/week (Mon or Thu)
        if d.weekday() in (0, 3) and rng.random() < 0.55:
            station, (lo, hi) = rng.choice(GAS_STATIONS)
            acct.transactions.append(Transaction(
                acct_id=acct.acct_id,
                trans_id=next_trans_id(tid),
                ttype=TYPE_DEBIT,
                amount=-round(rng.uniform(lo, hi), 2),
                when=d,
                description=station,
                bank_date_offset_days=bank_offset(rng),
            ))
        # Restaurants 2x/week average
        if rng.random() < 0.29:
            rest, (lo, hi) = rng.choice(RESTAURANTS)
            acct.transactions.append(Transaction(
                acct_id=acct.acct_id,
                trans_id=next_trans_id(tid),
                ttype=TYPE_DEBIT,
                amount=-round(rng.uniform(lo, hi), 2),
                when=d,
                description=rest,
                bank_date_offset_days=bank_offset(rng),
            ))
        # Online shopping ~1x/week
        if rng.random() < 0.14:
            shop, (lo, hi) = rng.choice(ONLINE_SHOPPING)
            acct.transactions.append(Transaction(
                acct_id=acct.acct_id,
                trans_id=next_trans_id(tid),
                ttype=TYPE_DEBIT,
                amount=-round(rng.uniform(lo, hi), 2),
                when=d,
                description=shop,
                bank_date_offset_days=bank_offset(rng),
            ))
        d += timedelta(days=1)

    # --- Occasional expenses ~1x per month ---
    for y, m in months:
        n = rng.randint(0, 2)
        for _ in range(n):
            day = rng.randint(1, 28)
            when = day_in_month(y, m, day)
            if when > end:
                continue
            desc, (lo, hi) = rng.choice(OCCASIONAL_EXPENSES)
            acct.transactions.append(Transaction(
                acct_id=acct.acct_id,
                trans_id=next_trans_id(tid),
                ttype=TYPE_DEBIT,
                amount=-round(rng.uniform(lo, hi), 2),
                when=when,
                description=desc,
                bank_date_offset_days=bank_offset(rng),
            ))

    # --- Paper checks for household services ~1-2 per month ---
    check_no = FIRST_CHECK_NO
    for y, m in months:
        n = rng.randint(1, 2)
        for _ in range(n):
            day = rng.randint(3, 27)
            when = day_in_month(y, m, day)
            if when > end:
                continue
            desc, (lo, hi) = rng.choice(CHECK_PAYEES)
            acct.transactions.append(Transaction(
                acct_id=acct.acct_id,
                trans_id=next_trans_id(tid),
                ttype=TYPE_CHECK,
                amount=-round(rng.uniform(lo, hi), 2),
                when=when,
                description=desc,
                check_no=check_no,
                # Checks take longer to clear
                bank_date_offset_days=rng.randint(4, 9) if rng.random() > 0.1 else rng.randint(14, 18),
            ))
            check_no += 1

    # --- Mark reconciliation status based on cutoff ---
    for t in acct.transactions:
        t.reconciled = t.when <= UNRECONCILED_CUTOFF

    # --- A few uncleared checks (exist in chk_trans but NOT in bank CSV yet) ---
    # Pick 2 recent checks and flag them as not-yet-cleared.
    recent_checks = [t for t in acct.transactions
                     if t.ttype == TYPE_CHECK and t.when > UNRECONCILED_CUTOFF]
    for t in recent_checks[:2]:
        t.appears_in_bank = False

    return acct


def generate_savings(rng: random.Random) -> Account:
    acct = Account(
        acct_id=SAVINGS_ACCT_ID,
        bank="First Meridian Bank",
        name="Household Savings",
        account_no="****9032",
        opening_balance=SAVINGS_OPENING_BALANCE,
    )
    tid = [0]
    start = months_ago(MONTHS_OF_HISTORY - 1)
    end = TODAY

    months = []
    y, m = start.year, start.month
    while (y, m) <= (end.year, end.month):
        months.append((y, m))
        m += 1
        if m > 12:
            m = 1
            y += 1

    for y, m in months:
        # Matching auto-transfer in
        when = day_in_month(y, m, 5)
        if when <= end:
            acct.transactions.append(Transaction(
                acct_id=acct.acct_id,
                trans_id=next_trans_id(tid),
                ttype=TYPE_DEPOSIT,
                amount=MONTHLY_AUTO_TRANSFER,
                when=when,
                description="TRANSFER FROM CHECKING ****4821",
                bank_date_offset_days=0,
            ))
        # Interest credit on last day of month
        interest_day = day_in_month(y, m, 28)
        if interest_day <= end:
            acct.transactions.append(Transaction(
                acct_id=acct.acct_id,
                trans_id=next_trans_id(tid),
                ttype=TYPE_DEPOSIT,
                amount=round(rng.uniform(1.80, 4.95), 2),
                when=interest_day,
                description="INTEREST PAID",
                bank_date_offset_days=0,
            ))

    # One ad-hoc withdrawal (emergency fund tap)
    random_month = rng.choice(months[4:-3]) if len(months) > 8 else months[len(months) // 2]
    when = day_in_month(random_month[0], random_month[1], rng.randint(10, 25))
    acct.transactions.append(Transaction(
        acct_id=acct.acct_id,
        trans_id=next_trans_id(tid),
        ttype=TYPE_DEBIT,
        amount=-750.00,
        when=when,
        description="TRANSFER TO CHECKING ****4821",
        bank_date_offset_days=0,
    ))

    for t in acct.transactions:
        t.reconciled = t.when <= UNRECONCILED_CUTOFF

    return acct


# ---------------------------------------------------------------------------
# Emitters
# ---------------------------------------------------------------------------


def write_sql(path: str, accounts: List[Account]) -> None:
    lines: List[str] = []
    lines.append("-- Auto-generated by tools/generate_test_data.py")
    lines.append("-- Populates chk_account and chk_trans with 18 months of")
    lines.append("-- synthetic household transaction data for screenshots.")
    lines.append("")
    lines.append("SET FOREIGN_KEY_CHECKS=0;")
    lines.append("TRUNCATE TABLE chk_bank_trans;")
    lines.append("TRUNCATE TABLE chk_bank_statement;")
    lines.append("TRUNCATE TABLE chk_trans;")
    lines.append("TRUNCATE TABLE chk_account;")
    lines.append("SET FOREIGN_KEY_CHECKS=1;")
    lines.append("")

    # Balances need to reflect the running total of all transactions
    for acct in accounts:
        total = acct.opening_balance + sum(t.amount for t in acct.transactions)
        reconciled_total = acct.opening_balance + sum(
            t.amount for t in acct.transactions if t.reconciled
        )
        lines.append(
            "INSERT INTO chk_account "
            "(chk_acct_id, chk_bank, chk_name, chk_account_no, chk_balance, "
            "chk_bank_balance, chk_is_archived) VALUES "
            f"({acct.acct_id}, '{sql_escape(acct.bank)}', "
            f"'{sql_escape(acct.name)}', '{sql_escape(acct.account_no)}', "
            f"{total:.2f}, {reconciled_total:.2f}, 'N');"
        )
    lines.append("")

    for acct in accounts:
        acct.transactions.sort(key=lambda t: (t.when, t.trans_id))
        for t in acct.transactions:
            date_int = int(t.when.strftime("%Y%m%d"))
            check_no_sql = str(t.check_no) if t.check_no is not None else "NULL"
            reconciled = "'Y'" if t.reconciled else "'N'"
            lines.append(
                "INSERT INTO chk_trans "
                "(chk_acct_id, chk_trans_id, chk_type, chk_no, chk_amount, "
                "chk_date, chk_description, chk_reconciled) VALUES "
                f"({t.acct_id}, {t.trans_id}, {t.ttype}, {check_no_sql}, "
                f"{t.amount:.2f}, {date_int}, "
                f"'{sql_escape(t.description)}', {reconciled});"
            )
        lines.append("")

    with open(path, "w", encoding="utf-8") as f:
        f.write("\n".join(lines))


@dataclass
class BankEvent:
    when: date
    amount: float
    description: str
    check_no: Optional[int]
    balance: float = 0.0  # running balance filled in after sorting


def build_bank_events(account: Account, rng: random.Random) -> List[BankEvent]:
    """Return a sorted list of BankEvent rows the bank would report.

    Mirrors most of the account's user transactions (with a posting-date
    offset), drops anything flagged as uncleared, and injects a few
    bank-only fees so reconciliation has surprises to catch.
    """
    events: List[BankEvent] = []
    for t in account.transactions:
        if not t.appears_in_bank:
            continue
        bank_when = t.when + timedelta(days=t.bank_date_offset_days)
        if bank_when > TODAY:
            bank_when = TODAY  # can't post in the future
        events.append(BankEvent(
            when=bank_when,
            amount=t.amount,
            description=t.description,
            check_no=t.check_no,
        ))

    # Inject a few bank-only fees spread across older months so the user has
    # something to discover when reconciling.
    if account.acct_id == CHECKING_ACCT_ID:
        start = months_ago(MONTHS_OF_HISTORY - 1)
        y, m = start.year, start.month
        fee_months = 0
        while (y, m) <= (TODAY.year, TODAY.month) and fee_months < 4:
            if rng.random() < 0.55:
                when = day_in_month(y, m, rng.randint(20, 27))
                if when <= TODAY:
                    desc, amt = rng.choice(BANK_ONLY_FEES)
                    events.append(BankEvent(
                        when=when,
                        amount=-amt,
                        description=desc,
                        check_no=None,
                    ))
                    fee_months += 1
            m += 1
            if m > 12:
                m = 1
                y += 1

    events.sort(key=lambda e: (e.when, e.description))

    # Running balance from the bank's perspective, carried across periods.
    balance = account.opening_balance
    for ev in events:
        balance += ev.amount
        ev.balance = balance

    return events


def period_key(d: date, period: str) -> str:
    """Return the statement period identifier a transaction belongs to.

    Months are labeled "YYYY-MM"; quarters are labeled "YYYY-QN".
    """
    if period == "quarter":
        q = (d.month - 1) // 3 + 1
        return f"{d.year}-Q{q}"
    return f"{d.year}-{d.month:02d}"


def write_statement_csv(path: str, events: List[BankEvent]) -> None:
    """Write a single bank statement CSV file for the given events."""
    with open(path, "w", encoding="utf-8", newline="") as f:
        writer = csv.writer(f)
        writer.writerow(["Date", "Check", "Description", "Debit", "Credit", "Balance"])
        for ev in events:
            debit = f"{-ev.amount:.2f}" if ev.amount < 0 else ""
            credit = f"{ev.amount:.2f}" if ev.amount > 0 else ""
            writer.writerow([
                ev.when.strftime("%-m/%-d/%Y"),
                str(ev.check_no) if ev.check_no is not None else "",
                ev.description,
                debit,
                credit,
                f"{ev.balance:.2f}",
            ])


def write_account_statements(
    out_dir: str, slug: str, account: Account, rng: random.Random, period: str
) -> List[str]:
    """Write one CSV per statement period for the account. Returns file paths."""
    events = build_bank_events(account, rng)

    # Group preserving order (events are already date-sorted).
    buckets: dict[str, List[BankEvent]] = {}
    for ev in events:
        key = period_key(ev.when, period)
        buckets.setdefault(key, []).append(ev)

    paths: List[str] = []
    for key, bucket in buckets.items():
        filename = f"{slug}-{key}.csv"
        path = os.path.join(out_dir, filename)
        write_statement_csv(path, bucket)
        paths.append(path)
    return paths


# ---------------------------------------------------------------------------
# Main
# ---------------------------------------------------------------------------


def main() -> None:
    parser = argparse.ArgumentParser(description="Generate synthetic WebCheckbook test data.")
    parser.add_argument("--seed", type=int, default=20260409,
                        help="Random seed for reproducible output.")
    parser.add_argument("--out", default=os.path.join(os.path.dirname(__file__), "output"),
                        help="Output directory (default: tools/output).")
    parser.add_argument("--period", choices=("month", "quarter"), default="month",
                        help="Statement period granularity (default: month).")
    args = parser.parse_args()

    os.makedirs(args.out, exist_ok=True)
    rng = random.Random(args.seed)

    checking = generate_checking(rng)
    savings = generate_savings(rng)

    sql_path = os.path.join(args.out, "seed-data.sql")
    write_sql(sql_path, [checking, savings])

    checking_paths = write_account_statements(
        args.out, "checking", checking, rng, args.period
    )
    savings_paths = write_account_statements(
        args.out, "savings", savings, rng, args.period
    )

    total_txns = len(checking.transactions) + len(savings.transactions)
    print(f"Wrote {sql_path}")
    print(f"Wrote {len(checking_paths)} checking statement(s) "
          f"({args.period}ly) to {args.out}")
    print(f"Wrote {len(savings_paths)} savings statement(s) "
          f"({args.period}ly) to {args.out}")
    print(f"Checking: {len(checking.transactions)} transactions, "
          f"final balance "
          f"${checking.opening_balance + sum(t.amount for t in checking.transactions):,.2f}")
    print(f"Savings:  {len(savings.transactions)} transactions, "
          f"final balance "
          f"${savings.opening_balance + sum(t.amount for t in savings.transactions):,.2f}")
    print(f"Total: {total_txns} transactions across {MONTHS_OF_HISTORY} months.")
    print()
    print("Next steps:")
    print(f"  mysql -u <user> -p checkbook < {sql_path}")
    print("  Then import each statement CSV via import.php in the browser,")
    print("  one period at a time, to mirror the real reconcile workflow.")


if __name__ == "__main__":
    main()
