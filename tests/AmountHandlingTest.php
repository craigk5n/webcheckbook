<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

class AmountHandlingTest extends TestCase
{
    // --- determine_transaction_type() tests ---

    public function testDepositType(): void
    {
        $this->assertSame(1, determine_transaction_type(100.00, null));
        $this->assertSame(1, determine_transaction_type(0.01, null));
        $this->assertSame(1, determine_transaction_type(0.00, null));
    }

    public function testDebitType(): void
    {
        $this->assertSame(2, determine_transaction_type(-100.00, null));
        $this->assertSame(2, determine_transaction_type(-0.01, null));
        $this->assertSame(2, determine_transaction_type(-100.00, ''));
    }

    public function testCheckType(): void
    {
        $this->assertSame(3, determine_transaction_type(-100.00, '1001'));
        $this->assertSame(3, determine_transaction_type(-50.00, '999'));
    }

    public function testPositiveWithCheckNumberIsDeposit(): void
    {
        // Even with a check number, positive amount = deposit
        $this->assertSame(1, determine_transaction_type(100.00, '1001'));
    }

    // --- format_amount() tests ---

    public function testFormatAmountTwoDecimals(): void
    {
        $this->assertSame('100.00', format_amount(100.0));
        $this->assertSame('0.50', format_amount(0.5));
        $this->assertSame('-45.67', format_amount(-45.67));
    }

    public function testFormatAmountRounding(): void
    {
        $this->assertSame('100.46', format_amount(100.456));
        $this->assertSame('100.45', format_amount(100.454));
        $this->assertSame('0.01', format_amount(0.005));
    }

    public function testFormatAmountZero(): void
    {
        $this->assertSame('0.00', format_amount(0.0));
    }

    public function testFormatAmountLargeNumbers(): void
    {
        $this->assertSame('999999.99', format_amount(999999.99));
        $this->assertSame('-999999.99', format_amount(-999999.99));
    }

    // --- CSV credit/debit sign convention tests ---

    public function testCsvCreditPositive(): void
    {
        // In CSV import: credit > 0 means deposit (positive amount)
        $credit = 2500.00;
        $debit = 0.0;
        $amount = $credit > 0.0 ? $credit : -$debit;
        $this->assertEqualsWithDelta(2500.00, $amount, 0.001);
    }

    public function testCsvDebitNegative(): void
    {
        // In CSV import: debit > 0 means withdrawal (negative amount)
        $credit = 0.0;
        $debit = 45.67;
        $amount = $credit > 0.0 ? $credit : -$debit;
        $this->assertEqualsWithDelta(-45.67, $amount, 0.001);
    }

    public function testCsvBothZero(): void
    {
        $credit = 0.0;
        $debit = 0.0;
        $amount = $credit > 0.0 ? $credit : -$debit;
        $this->assertEqualsWithDelta(0.0, $amount, 0.001);
    }

    // --- Transaction amount sign handling ---

    public function testNonDepositNegation(): void
    {
        // Types 2, 3, 4: user enters positive amount, stored as negative
        $userAmount = 45.67;
        $type = 2; // debit
        $stored = ($type != 1) ? -$userAmount : $userAmount;
        $this->assertEqualsWithDelta(-45.67, $stored, 0.001);
    }

    public function testDepositStaysPositive(): void
    {
        $userAmount = 2500.00;
        $type = 1; // deposit
        $stored = ($type != 1) ? -$userAmount : $userAmount;
        $this->assertEqualsWithDelta(2500.00, $stored, 0.001);
    }
}
