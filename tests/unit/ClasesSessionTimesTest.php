<?php

use CodeIgniter\Test\CIUnitTestCase;
use App\Services\ClasesService;

/**
 * Regresión: la hora de una sesión debía guardarse EXACTAMENTE como se
 * introduce y nunca acabar en 00:00.
 *
 * Bug histórico (informe test-app.md): "Introduje 10:00–11:00 pero la sesión
 * se guardó como 00:00–01:00". Causa raíz: el formulario de pantalla completa
 * (/clases/nueva → createSession) no validaba nada en servidor y insertaba
 * `start_time` en crudo; un valor vacío/malformado → MySQL lo coercía a
 * 00:00:00. Además updateSession() ponía start_time = NULL con hora vacía.
 *
 * Estos tests fijan el contrato:
 *   - normalizeTime(): parsea/valida una hora a 'HH:MM'.
 *   - resolveTimes(): start obligatorio, end opcional (= start+1h), end > start.
 *   - createSession / quickCreate / updateSession rechazan horas inválidas
 *     y persisten las válidas sin alterarlas.
 *
 * Los tests marcados @group db requieren BD viva (ejecutar dentro de Docker).
 */
final class ClasesSessionTimesTest extends CIUnitTestCase
{
    // ────────────────────────────────────────────────────────────────
    //  normalizeTime()  — lógica pura, sin BD
    // ────────────────────────────────────────────────────────────────

    /**
     * @dataProvider validTimeProvider
     */
    public function testNormalizeTimeAcceptsValid(string $in, string $expected): void
    {
        $this->assertSame($expected, ClasesService::normalizeTime($in));
    }

    public static function validTimeProvider(): array
    {
        return [
            'ya normalizada'      => ['10:00', '10:00'],
            'con segundos'        => ['10:00:00', '10:00'],
            'sin cero a la izq'   => ['9:05', '09:05'],
            'con espacios'        => ['  08:30  ', '08:30'],
            'medianoche'          => ['00:00', '00:00'],
            'último minuto'       => ['23:59', '23:59'],
            'tarde con segundos'  => ['18:45:30', '18:45'],
        ];
    }

    /**
     * @dataProvider invalidTimeProvider
     */
    public function testNormalizeTimeRejectsInvalid(?string $in): void
    {
        $this->assertNull(ClasesService::normalizeTime($in));
    }

    public static function invalidTimeProvider(): array
    {
        return [
            'null'            => [null],
            'vacío'           => [''],
            'solo espacios'   => ['   '],
            'texto'           => ['mañana'],
            'sin minutos'     => ['10'],
            'hora fuera rango' => ['24:00'],
            'minuto fuera rango' => ['10:60'],
            'formato raro'    => ['1000'],
            'negativa'        => ['-1:00'],
        ];
    }

    // ────────────────────────────────────────────────────────────────
    //  resolveTimes()  — lógica pura vía reflexión
    // ────────────────────────────────────────────────────────────────

    private function resolveTimes(array $data): array
    {
        $svc = (new \ReflectionClass(ClasesService::class))->newInstanceWithoutConstructor();
        $m   = new \ReflectionMethod(ClasesService::class, 'resolveTimes');
        $m->setAccessible(true);
        return $m->invoke($svc, $data);
    }

    public function testResolveTimesHappyPath(): void
    {
        $r = $this->resolveTimes(['start_time' => '10:00', 'end_time' => '11:30']);
        $this->assertNull($r['error']);
        $this->assertSame('10:00', $r['start']);
        $this->assertSame('11:30', $r['end']);
    }

    public function testResolveTimesFillsEndOneHourLater(): void
    {
        $r = $this->resolveTimes(['start_time' => '10:00', 'end_time' => '']);
        $this->assertNull($r['error']);
        $this->assertSame('11:00', $r['end']);
    }

    public function testResolveTimesRejectsEmptyStart(): void
    {
        $r = $this->resolveTimes(['start_time' => '', 'end_time' => '11:00']);
        $this->assertNotNull($r['error']);
        $this->assertNull($r['start']);
    }

    public function testResolveTimesRejectsMalformedStart(): void
    {
        $r = $this->resolveTimes(['start_time' => '99:99']);
        $this->assertNotNull($r['error']);
    }

    public function testResolveTimesRejectsEndBeforeStart(): void
    {
        $r = $this->resolveTimes(['start_time' => '12:00', 'end_time' => '11:00']);
        $this->assertNotNull($r['error']);
    }

    public function testResolveTimesRejectsEndEqualToStart(): void
    {
        $r = $this->resolveTimes(['start_time' => '12:00', 'end_time' => '12:00']);
        $this->assertNotNull($r['error']);
    }

    public function testResolveTimesAllowsMidnightEnd(): void
    {
        // Sesión que cruza medianoche: fin '00:00' es válido.
        $r = $this->resolveTimes(['start_time' => '23:00', 'end_time' => '00:00']);
        $this->assertNull($r['error']);
        $this->assertSame('00:00', $r['end']);
    }

    // ────────────────────────────────────────────────────────────────
    //  Puertas de validación de los puntos de entrada (sin persistencia)
    //
    //  createSession() / quickCreate() validan las horas ANTES de tocar
    //  la BD, así que estos casos de rechazo no necesitan @group db.
    // ────────────────────────────────────────────────────────────────

    private function service(): ClasesService
    {
        return new ClasesService();
    }

    public function testCreateSessionRejectsEmptyStartTime(): void
    {
        $res = $this->service()->createSession([
            'type' => 'single', 'title' => 'X', 'session_date' => '2026-01-10',
            'start_time' => '', 'end_time' => '11:00',
        ], 1);
        $this->assertFalse($res['success']);
        $this->assertStringContainsStringIgnoringCase('hora de inicio', $res['error']);
    }

    public function testCreateSessionRejectsMalformedStartTime(): void
    {
        $res = $this->service()->createSession([
            'type' => 'single', 'title' => 'X', 'session_date' => '2026-01-10',
            'start_time' => '99:99',
        ], 1);
        $this->assertFalse($res['success']);
    }

    public function testCreateSessionRejectsEndBeforeStart(): void
    {
        $res = $this->service()->createSession([
            'type' => 'single', 'title' => 'X', 'session_date' => '2026-01-10',
            'start_time' => '12:00', 'end_time' => '11:00',
        ], 1);
        $this->assertFalse($res['success']);
        $this->assertStringContainsStringIgnoringCase('posterior', $res['error']);
    }

    public function testCreateSessionRejectsMissingTitle(): void
    {
        $res = $this->service()->createSession([
            'type' => 'single', 'title' => '   ', 'session_date' => '2026-01-10',
            'start_time' => '10:00',
        ], 1);
        $this->assertFalse($res['success']);
    }

    public function testCreateSessionRejectsMissingDate(): void
    {
        $res = $this->service()->createSession([
            'type' => 'single', 'title' => 'X', 'session_date' => '',
            'start_time' => '10:00',
        ], 1);
        $this->assertFalse($res['success']);
    }

    public function testQuickCreateRejectsEmptyStartTime(): void
    {
        $res = $this->service()->quickCreate([
            'type' => 'single', 'title' => 'X', 'session_date' => '2026-01-10',
            'start_time' => '',
        ], 1);
        $this->assertFalse($res['success']);
    }

    public function testRecurringRejectsMalformedStartTime(): void
    {
        $res = $this->service()->createSession([
            'type' => 'recurring', 'title' => 'X',
            'recurrence_days' => [1, 3], 'recurrence_start' => '2026-01-05',
            'recurrence_end' => '2026-02-05', 'start_time' => 'nope',
        ], 1);
        $this->assertFalse($res['success']);
    }

    /**
     * Salvaguarda de bajo nivel: insertSingle() nunca debe escribir una hora
     * inválida en BD (start_time es NOT NULL). Lanza antes de tocar el modelo.
     */
    public function testInsertSingleThrowsOnInvalidTime(): void
    {
        $svc = (new \ReflectionClass(ClasesService::class))->newInstanceWithoutConstructor();
        $m   = new \ReflectionMethod(ClasesService::class, 'insertSingle');
        $m->setAccessible(true);

        $this->expectException(\InvalidArgumentException::class);
        $m->invoke($svc, ['title' => 'X', 'session_date' => '2026-01-10', 'start_time' => ''], 1);
    }
}
