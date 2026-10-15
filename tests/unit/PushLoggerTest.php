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

    // ── Fichero propio (independiente del umbral de logs de producción) ───

    private string $dir = '';

    protected function setUp(): void
    {
        parent::setUp();
        $this->dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'pushlog_' . bin2hex(random_bytes(4));
        mkdir($this->dir);
        PushLogger::useDirectory($this->dir);
    }

    protected function tearDown(): void
    {
        PushLogger::useDirectory(null);
        foreach (glob($this->dir . '/*') ?: [] as $f) {
            @unlink($f);
        }
        @rmdir($this->dir);
        parent::tearDown();
    }

    public function testInfoWarningAndErrorGoToTheDailyFile(): void
    {
        PushLogger::info('batch', ['notif' => 42, 'ok' => 2]);
        PushLogger::warning('delivery_failed', ['http' => 410]);
        PushLogger::error('vapid_rejected', ['subs' => 3], new \RuntimeException('boom'));

        $lines = file(PushLogger::fileFor(date('Y-m-d')), FILE_IGNORE_NEW_LINES);
        $this->assertCount(3, $lines);
        $this->assertMatchesRegularExpression('/^\S+ INFO \[push\] evt=batch notif=42 ok=2$/', $lines[0]);
        $this->assertStringContainsString(' WARNING [push] evt=delivery_failed http=410', $lines[1]);
        $this->assertStringContainsString(' ERROR [push] evt=vapid_rejected subs=3 exception="RuntimeException: boom"', $lines[2]);
    }

    public function testDebugDoesNotFillTheDailyFile(): void
    {
        PushLogger::debug('release_client', ['via' => 'litespeed']);
        $this->assertFileDoesNotExist(PushLogger::fileFor(date('Y-m-d')));
    }

    public function testOldFilesArePrunedWhenANewDayStarts(): void
    {
        $old    = PushLogger::fileFor(date('Y-m-d', time() - 40 * 86400));
        $recent = PushLogger::fileFor(date('Y-m-d', time() - 5 * 86400));
        file_put_contents($old, 'x');
        file_put_contents($recent, 'x');

        PushLogger::info('batch'); // primer log del día → poda

        $this->assertFileDoesNotExist($old);
        $this->assertFileExists($recent);
    }

    public function testAnUnwritableDirectoryNeverThrows(): void
    {
        PushLogger::useDirectory($this->dir . '/no/existe');
        PushLogger::error('flush_failed');
        $this->addToAssertionCount(1);
    }

    public function testLongValuesAreTruncated(): void
    {
        $line = PushLogger::format('x', ['msg' => str_repeat('a', 500)]);
        $this->assertLessThan(260, mb_strlen($line));
        $this->assertStringContainsString('…', $line);
    }
}
