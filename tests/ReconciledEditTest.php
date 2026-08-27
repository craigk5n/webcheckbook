<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Covers the helpers behind editing a reconciled transaction: normalizing a
 * check number, describing what is about to change, and summarizing the bank
 * statement line the transaction was matched to.
 */
class ReconciledEditTest extends TestCase
{
    // --- normalize_check_number() tests ---

    public function testNormalizeCheckNumberStripsLeadingZeros(): void
    {
        $this->assertSame('123', normalize_check_number('0123'));
        $this->assertSame('123', normalize_check_number('123'));
        $this->assertSame('0', normalize_check_number('0000'));
    }

    public function testNormalizeCheckNumberTrimsWhitespace(): void
    {
        $this->assertSame('42', normalize_check_number('  42  '));
    }

    public function testNormalizeCheckNumberHandlesEmptyValues(): void
    {
        $this->assertSame('', normalize_check_number(''));
        $this->assertSame('', normalize_check_number(null));
        $this->assertSame('', normalize_check_number('   '));
    }

    public function testNormalizeCheckNumberAcceptsIntegers(): void
    {
        $this->assertSame('1234', normalize_check_number(1234));
    }

    public function testNormalizeCheckNumberLeavesNonNumericAlone(): void
    {
        $this->assertSame('12A', normalize_check_number('12A'));
    }

    // --- describe_reconciled_changes() tests ---

    public function testNoChangesReturnsEmptyArray(): void
    {
        $stored = ['date' => '20240115', 'num' => '1234'];
        $this->assertSame([], describe_reconciled_changes($stored, $stored));
    }

    public function testEquivalentCheckNumberIsNotAChange(): void
    {
        $changes = describe_reconciled_changes(
            ['date' => '20240115', 'num' => '1234'],
            ['date' => '20240115', 'num' => '01234']
        );
        $this->assertSame([], $changes);
    }

    public function testDateChangeIsDescribed(): void
    {
        $changes = describe_reconciled_changes(
            ['date' => '20240115', 'num' => '1234'],
            ['date' => '20240117', 'num' => '1234']
        );

        $this->assertCount(1, $changes);
        $this->assertSame('date', $changes[0]['field']);
        $this->assertSame('1/15/2024', $changes[0]['from']);
        $this->assertSame('1/17/2024', $changes[0]['to']);
    }

    public function testCheckNumberChangeIsDescribed(): void
    {
        $changes = describe_reconciled_changes(
            ['date' => '20240115', 'num' => '1234'],
            ['date' => '20240115', 'num' => '1250']
        );

        $this->assertCount(1, $changes);
        $this->assertSame('num', $changes[0]['field']);
        $this->assertSame('1234', $changes[0]['from']);
        $this->assertSame('1250', $changes[0]['to']);
    }

    public function testClearedCheckNumberShowsNonePlaceholder(): void
    {
        $changes = describe_reconciled_changes(
            ['date' => '20240115', 'num' => '1234'],
            ['date' => '20240115', 'num' => '']
        );

        $this->assertCount(1, $changes);
        $this->assertSame('(none)', $changes[0]['to']);
    }

    public function testAddedCheckNumberShowsNonePlaceholderAsFrom(): void
    {
        $changes = describe_reconciled_changes(
            ['date' => '20240115', 'num' => ''],
            ['date' => '20240115', 'num' => '1234']
        );

        $this->assertCount(1, $changes);
        $this->assertSame('(none)', $changes[0]['from']);
        $this->assertSame('1234', $changes[0]['to']);
    }

    public function testBothFieldsChangedAreBothDescribed(): void
    {
        $changes = describe_reconciled_changes(
            ['date' => '20240115', 'num' => '1234'],
            ['date' => '20240220', 'num' => '1250']
        );

        $this->assertCount(2, $changes);
        $this->assertSame('date', $changes[0]['field']);
        $this->assertSame('num', $changes[1]['field']);
    }

    public function testMissingKeysAreTreatedAsEmpty(): void
    {
        $changes = describe_reconciled_changes([], []);
        $this->assertSame([], $changes);
    }

    // --- format_bank_trans_summary() tests ---

    public function testBankSummaryIncludesAllParts(): void
    {
        $summary = format_bank_trans_summary([
            'date' => '20240115',
            'num' => '1234',
            'amount' => -45.5,
            'description' => 'HARDWARE STORE',
        ]);

        $this->assertStringContainsString('1/15/2024', $summary);
        $this->assertStringContainsString('ChkNo 1234', $summary);
        $this->assertStringContainsString('-45.50', $summary);
        $this->assertStringContainsString('HARDWARE STORE', $summary);
    }

    public function testBankSummaryOmitsMissingCheckNumberAndDescription(): void
    {
        $summary = format_bank_trans_summary([
            'date' => '20240115',
            'num' => '',
            'amount' => 100.0,
            'description' => '',
        ]);

        $this->assertStringNotContainsString('ChkNo', $summary);
        $this->assertSame("1/15/2024 \u{00B7} 100.00", $summary);
    }
}
