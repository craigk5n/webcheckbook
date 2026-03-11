<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

class CsvParsingTest extends TestCase
{
    // --- parse_csv_headers() tests ---

    public function testParseStandardHeaders(): void
    {
        $headers = ['Posting Date', 'Check', 'Description', 'Debit', 'Credit', 'Status', 'Balance'];
        $indices = parse_csv_headers($headers);

        $this->assertSame(0, $indices['date']);
        $this->assertSame(1, $indices['check']);
        $this->assertSame(2, $indices['description']);
        $this->assertSame(3, $indices['debit']);
        $this->assertSame(4, $indices['credit']);
        $this->assertSame(5, $indices['status']);
        $this->assertSame(6, $indices['balance']);
    }

    public function testParseCaseInsensitiveHeaders(): void
    {
        $headers = ['DATE', 'CHECK NUMBER', 'DESCRIPTION', 'DEBIT AMOUNT', 'CREDIT AMOUNT', 'STATUS', 'BALANCE'];
        $indices = parse_csv_headers($headers);

        $this->assertSame(0, $indices['date']);
        $this->assertSame(1, $indices['check']);
        $this->assertSame(2, $indices['description']);
        $this->assertSame(3, $indices['debit']);
        $this->assertSame(4, $indices['credit']);
        $this->assertSame(5, $indices['status']);
        $this->assertSame(6, $indices['balance']);
    }

    public function testParsePartialHeaders(): void
    {
        $headers = ['Transaction Date', 'Amount', 'Memo'];
        $indices = parse_csv_headers($headers);

        $this->assertSame(0, $indices['date']);
        $this->assertSame(-1, $indices['check']);
        $this->assertSame(-1, $indices['description']);
        $this->assertSame(-1, $indices['debit']);
        $this->assertSame(-1, $indices['credit']);
        $this->assertSame(-1, $indices['balance']);
    }

    public function testParseEmptyHeaders(): void
    {
        $indices = parse_csv_headers([]);
        foreach ($indices as $val) {
            $this->assertSame(-1, $val);
        }
    }

    public function testParseHeadersWithWhitespace(): void
    {
        $headers = ['  Posting Date  ', ' Check ', ' Description ', ' Debit ', ' Credit ', ' Status ', ' Balance '];
        $indices = parse_csv_headers($headers);

        $this->assertSame(0, $indices['date']);
        $this->assertSame(1, $indices['check']);
        $this->assertSame(2, $indices['description']);
    }

    // --- validate_csv_headers() tests ---

    public function testValidateAllPresent(): void
    {
        $indices = ['date' => 0, 'check' => 1, 'description' => 2, 'debit' => 3, 'credit' => 4, 'status' => 5, 'balance' => 6];
        $errors = validate_csv_headers($indices);
        $this->assertEmpty($errors);
    }

    public function testValidateMissingRequired(): void
    {
        $indices = ['date' => 0, 'check' => -1, 'description' => 2, 'debit' => -1, 'credit' => 4, 'status' => -1, 'balance' => 6];
        $errors = validate_csv_headers($indices);
        $this->assertCount(2, $errors); // check and debit missing
        $this->assertStringContainsString('check', $errors[0]);
        $this->assertStringContainsString('debit', $errors[1]);
    }

    public function testValidateAllMissing(): void
    {
        $indices = ['date' => -1, 'check' => -1, 'description' => -1, 'debit' => -1, 'credit' => -1, 'status' => -1, 'balance' => -1];
        $errors = validate_csv_headers($indices);
        $this->assertCount(6, $errors); // 6 required fields
    }

    // --- parse_csv_row() tests ---

    public function testParseValidDebitRow(): void
    {
        $indices = ['date' => 0, 'check' => 1, 'description' => 2, 'debit' => 3, 'credit' => 4, 'balance' => 5, 'status' => -1];
        $data = ['01/15/2024', '', 'GROCERY STORE', '45.67', '', '1000.00'];
        $result = parse_csv_row($data, $indices, 6, 2);

        $this->assertNull($result['error']);
        $this->assertNotNull($result['transaction']);
        $this->assertSame('20240115', $result['transaction']['date']);
        $this->assertEqualsWithDelta(-45.67, $result['transaction']['amount'], 0.001);
        $this->assertNull($result['transaction']['no']);
        $this->assertSame('GROCERY STORE', $result['transaction']['desc']);
    }

    public function testParseValidCreditRow(): void
    {
        $indices = ['date' => 0, 'check' => 1, 'description' => 2, 'debit' => 3, 'credit' => 4, 'balance' => 5, 'status' => -1];
        $data = ['01/15/2024', '', 'PAYROLL DEPOSIT', '', '2500.00', '3500.00'];
        $result = parse_csv_row($data, $indices, 6, 2);

        $this->assertNull($result['error']);
        $this->assertNotNull($result['transaction']);
        $this->assertEqualsWithDelta(2500.00, $result['transaction']['amount'], 0.001);
    }

    public function testParseRowWithCheckNumber(): void
    {
        $indices = ['date' => 0, 'check' => 1, 'description' => 2, 'debit' => 3, 'credit' => 4, 'balance' => 5, 'status' => -1];
        $data = ['01/15/2024', '1234', 'CHECK PAYMENT', '100.00', '', '900.00'];
        $result = parse_csv_row($data, $indices, 6, 2);

        $this->assertNull($result['error']);
        $this->assertSame('1234', $result['transaction']['no']);
    }

    public function testParseRowZeroAmountSkipped(): void
    {
        $indices = ['date' => 0, 'check' => 1, 'description' => 2, 'debit' => 3, 'credit' => 4, 'balance' => 5, 'status' => -1];
        $data = ['01/15/2024', '', 'ZERO TRANS', '', '', '1000.00'];
        $result = parse_csv_row($data, $indices, 6, 2);

        $this->assertNull($result['error']);
        $this->assertNull($result['transaction']);
    }

    public function testParseRowWrongColumnCount(): void
    {
        $indices = ['date' => 0, 'check' => 1, 'description' => 2, 'debit' => 3, 'credit' => 4, 'balance' => 5, 'status' => -1];
        $data = ['01/15/2024', '', 'SHORT ROW'];
        $result = parse_csv_row($data, $indices, 6, 5);

        $this->assertNotNull($result['error']);
        $this->assertStringContainsString('Line 5', $result['error']);
        $this->assertStringContainsString('3 columns instead of 6', $result['error']);
    }

    public function testParseRowInvalidDateFormat(): void
    {
        $indices = ['date' => 0, 'check' => 1, 'description' => 2, 'debit' => 3, 'credit' => 4, 'balance' => 5, 'status' => -1];
        $data = ['2024-01-15', '', 'BAD DATE', '10.00', '', '990.00'];
        $result = parse_csv_row($data, $indices, 6, 3);

        $this->assertNotNull($result['error']);
        $this->assertStringContainsString('Invalid date format', $result['error']);
    }

    public function testParseRowInvalidDate(): void
    {
        $indices = ['date' => 0, 'check' => 1, 'description' => 2, 'debit' => 3, 'credit' => 4, 'balance' => 5, 'status' => -1];
        $data = ['02/30/2024', '', 'IMPOSSIBLE DATE', '10.00', '', '990.00'];
        $result = parse_csv_row($data, $indices, 6, 4);

        $this->assertNotNull($result['error']);
        $this->assertStringContainsString('Invalid date', $result['error']);
    }

    public function testParseRowTwoDigitYear(): void
    {
        $indices = ['date' => 0, 'check' => 1, 'description' => 2, 'debit' => 3, 'credit' => 4, 'balance' => 5, 'status' => -1];
        $data = ['01/15/24', '', 'SHORT YEAR', '10.00', '', '990.00'];
        $result = parse_csv_row($data, $indices, 6, 2);

        $this->assertNull($result['error']);
        $this->assertSame('20240115', $result['transaction']['date']);
    }

    public function testParseRowDescriptionTooLong(): void
    {
        $indices = ['date' => 0, 'check' => 1, 'description' => 2, 'debit' => 3, 'credit' => 4, 'balance' => 5, 'status' => -1];
        $longDesc = str_repeat('A', 101);
        $data = ['01/15/2024', '', $longDesc, '10.00', '', '990.00'];
        $result = parse_csv_row($data, $indices, 6, 2);

        $this->assertNotNull($result['error']);
        $this->assertStringContainsString('Description too long', $result['error']);
    }

    public function testParseRowDescriptionUppercased(): void
    {
        $indices = ['date' => 0, 'check' => 1, 'description' => 2, 'debit' => 3, 'credit' => 4, 'balance' => 5, 'status' => -1];
        $data = ['01/15/2024', '', 'lowercase store', '10.00', '', '990.00'];
        $result = parse_csv_row($data, $indices, 6, 2);

        $this->assertNull($result['error']);
        $this->assertSame('LOWERCASE STORE', $result['transaction']['desc']);
    }

    // --- Full CSV pipeline test ---

    public function testFullCsvPipeline(): void
    {
        $headers = ['Posting Date', 'Check', 'Description', 'Debit', 'Credit', 'Status', 'Balance'];
        $indices = parse_csv_headers($headers);
        $errors = validate_csv_headers($indices);
        $this->assertEmpty($errors);

        $rows = [
            ['01/15/2024', '', 'COSTCO WHOLESALE', '240.45', '', 'Posted', '5000.00'],
            ['01/16/2024', '1001', 'CHECK PAYMENT', '500.00', '', 'Posted', '4500.00'],
            ['01/17/2024', '', 'PAYROLL', '', '3000.00', 'Posted', '7500.00'],
            ['01/18/2024', '', 'ZERO TRANS', '', '', 'Posted', '7500.00'],
        ];

        $transactions = [];
        foreach ($rows as $i => $row) {
            $result = parse_csv_row($row, $indices, count($headers), $i + 2);
            $this->assertNull($result['error']);
            if ($result['transaction'] !== null) {
                $transactions[] = $result['transaction'];
            }
        }

        $this->assertCount(3, $transactions); // Zero-amount row skipped

        // Check COSTCO debit
        $this->assertSame('20240115', $transactions[0]['date']);
        $this->assertEqualsWithDelta(-240.45, $transactions[0]['amount'], 0.001);
        $this->assertSame('COSTCO WHOLESALE', $transactions[0]['desc']);

        // Check with check number
        $this->assertSame('1001', $transactions[1]['no']);
        $this->assertEqualsWithDelta(-500.00, $transactions[1]['amount'], 0.001);

        // Deposit
        $this->assertEqualsWithDelta(3000.00, $transactions[2]['amount'], 0.001);
    }
}
