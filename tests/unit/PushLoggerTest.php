<?php

use App\Libraries\Push\PushLogger;
use CodeIgniter\Test\CIUnitTestCase;

/** Formato de log único del push: debe ser estable porque se busca con grep en producción. */
final class PushLoggerTest extends CIUnitTestCase
{
    public function testLineStartsWithTagAndEvent(): void
    {
        $this->assertSame('[push] evt=batch', PushLogger::format('batch'));
    }

    public function testContextIsKeyValueInOrder(): void
    {
        $this->assertSame('[push] evt=batch notif=42 ok=2 ms=130', PushLogger::format('batch', ['notif' => 42, 'ok' => 2, 'ms' => 130]));
    }

    public function testNullBoolAndSpaces(): void
    {
        $line = PushLogger::format('x', ['a' => null, 'b' => true, 'c' => false, 'd' => 'dos palabras']);
        $this->assertSame('[push] evt=x a=- b=yes c=no d="dos palabras"', $line);
    }

    public function testNewlinesCannotForgeLogLines(): void
    {
        $line = PushLogger::format('x', ['msg' => "hola\n[push] evt=fake"]);
        $this->assertStringNotContainsString("\n", $line);
    }

    public function testLongValuesAreTruncated(): void
    {
        $line = PushLogger::format('x', ['msg' => str_repeat('a', 500)]);
        $this->assertLessThan(260, mb_strlen($line));
        $this->assertStringContainsString('…', $line);
    }
}
