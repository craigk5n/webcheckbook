<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

class InputValidationTest extends TestCase
{
    protected function setUp(): void
    {
        // Clear superglobals before each test
        $_POST = [];
        $_GET = [];
    }

    // --- getPostValue() tests ---

    public function testGetPostValueExists(): void
    {
        $_POST['name'] = 'John';
        $this->assertSame('John', getPostValue('name'));
    }

    public function testGetPostValueEmpty(): void
    {
        $_POST['name'] = '';
        $this->assertNull(getPostValue('name'));
    }

    public function testGetPostValueMissing(): void
    {
        $this->assertNull(getPostValue('nonexistent'));
    }

    // --- getGetValue() tests ---

    public function testGetGetValueExists(): void
    {
        $_GET['id'] = '42';
        $this->assertSame('42', getGetValue('id'));
    }

    public function testGetGetValueMissing(): void
    {
        $this->assertNull(getGetValue('nonexistent'));
    }

    // --- getValue() tests ---

    public function testGetValuePostOverGet(): void
    {
        $_POST['field'] = 'from_post';
        $_GET['field'] = 'from_get';
        $this->assertSame('from_post', getValue('field'));
    }

    public function testGetValueFallsBackToGet(): void
    {
        $_GET['field'] = 'from_get';
        $this->assertSame('from_get', getValue('field'));
    }

    public function testGetValueWithFormatMatch(): void
    {
        $_GET['num'] = '12345';
        $this->assertSame('12345', getValue('num', '[0-9]+'));
    }

    public function testGetValueWithFormatMismatch(): void
    {
        $_GET['num'] = 'abc';
        $this->assertSame('', getValue('num', '[0-9]+'));
    }

    public function testGetValueWithFormatFatal(): void
    {
        $_GET['num'] = 'abc';
        $this->expectException(Exception::class);
        getValue('num', '[0-9]+', true);
    }

    // --- getIntValue() tests ---

    public function testGetIntValueValid(): void
    {
        $_GET['id'] = '42';
        $this->assertSame(42, getIntValue('id'));
    }

    public function testGetIntValueNegative(): void
    {
        $_GET['id'] = '-5';
        $this->assertSame(-5, getIntValue('id'));
    }

    public function testGetIntValueInvalid(): void
    {
        $_GET['id'] = 'abc';
        $this->assertSame('', getIntValue('id'));
    }

    public function testGetIntValueEmpty(): void
    {
        $this->assertSame('', getIntValue('nonexistent'));
    }

    public function testGetIntValueFatal(): void
    {
        $_GET['id'] = 'abc';
        $this->expectException(Exception::class);
        getIntValue('id', true);
    }

    // --- SQL injection attempts should be caught ---

    public function testGetIntValueRejectsSqlInjection(): void
    {
        $_GET['id'] = '1; DROP TABLE chk_trans;--';
        $this->assertSame('', getIntValue('id'));
    }

    public function testGetIntValueRejectsUnionInjection(): void
    {
        $_GET['id'] = '1 UNION SELECT * FROM chk_account';
        $this->assertSame('', getIntValue('id'));
    }
}
