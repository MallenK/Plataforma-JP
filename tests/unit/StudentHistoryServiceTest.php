<?php

use App\Services\BonoLedgerService as L;
use App\Services\StudentHistoryService as H;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * Historial completo del alumno — partes puras.
 */
final class StudentHistoryServiceTest extends CIUnitTestCase
{
    private const NB = "\u{00A0}";

    public function testFechaRealDelCargoOCobro(): void
    {
        // Registrado el mismo día: fecha y hora de registro
        $this->assertSame('2026-10-10 13:42:00', H::when('2026-10-10', '2026-10-10 13:42:00'));
        // Registrado otro día (arranque de Finanzas): la fecha real
        $this->assertSame('2026-09-14 00:00:00', H::when('2026-09-14', '2026-10-10 13:31:00'));
        $this->assertSame('2026-10-10 09:00:00', H::when(null, '2026-10-10 09:00:00'));
    }

    public function testDescribeCambiosDeAuditoriaEnLenguajeLlano(): void
    {
        $this->assertSame('saldo de sesiones 5 → 4',
            H::describeChange('{"sessions_remaining":"5"}', '{"sessions_remaining":4}'));
        $this->assertSame('precio pagado 100,00' . self::NB . '€ → 160,50' . self::NB . '€; precio confirmado',
            H::describeChange('{"price_cents":"10000","price_estimated":"1"}', '{"price_cents":16050,"price_estimated":0}'));
        $this->assertSame('caducidad 14/10/2026 → 21/10/2026',
            H::describeChange('{"expires_at":"2026-10-14"}', '{"expires_at":"2026-10-21"}'));
        $this->assertSame('', H::describeChange(null, null));
    }

    public function testPrecioConfirmadoSinJerga(): void
    {
        // El cierre inicial solo cambia price_estimated 1 → 0: «Precio confirmado» con el importe del bono
        $this->assertSame(['Precio confirmado', 'se confirma que pagó 180,00' . self::NB . '€'],
            H::describeAudit('update', '{"price_estimated":"1"}', '{"price_estimated":0}', 18000));
        $this->assertSame(['Cambio', 'saldo de sesiones 3 → 2'],
            H::describeAudit('update', '{"sessions_remaining":3}', '{"sessions_remaining":2}'));
    }

    public function testTextoDeSaldoEconomico(): void
    {
        $this->assertSame('Debe 180,00' . self::NB . '€', H::balanceText(18000));
        $this->assertSame('Al día', H::balanceText(0));
        $this->assertSame('A su favor 20,00' . self::NB . '€', H::balanceText(-2000));
    }

    public function testFilasCsvConSaldo(): void
    {
        $rows = H::toCsvRows([
            ['at' => '2026-10-10 13:42:00', 'cat' => 'economico', 'event' => 'Pago recibido', 'detail' => 'Bizum', 'cents' => 2000, 'who' => 'Ana', 'link' => null, 'saldo' => 'Debe 10,00' . self::NB . '€'],
            ['at' => '2026-10-11 09:05:00', 'cat' => 'clases', 'event' => 'Clase · Presente', 'detail' => 'Individual', 'cents' => null, 'who' => null, 'link' => null, 'saldo' => null],
        ]);
        $this->assertSame(['Fecha', 'Hora', 'Categoría', 'Evento', 'Detalle', 'Importe (€)', 'Saldo después', 'Hecho por'], $rows[0]);
        $this->assertSame(['10/10/2026', '13:42', 'Económico', 'Pago recibido', 'Bizum', '20,00', 'Debe 10,00 €', 'Ana'], $rows[1]);
        $this->assertSame(['11/10/2026', '09:05', 'Clases', 'Clase · Presente', 'Individual', '', '', ''], $rows[2]);
    }

    private function bono(array $over = []): array
    {
        return array_merge(['id' => 210074, 'label' => 'Bono 4 #210074', 'sessions_total' => 4, 'sessions_remaining' => 0,
                            'created_at' => '2026-09-14 17:43:34', 'created_by' => 'Berta', 'expires_at' => '2026-10-14'], $over);
    }

    private function move(int $id, string $type, int $delta, string $at, ?int $session = null, string $note = '', string $who = 'Valèria'): array
    {
        return ['id' => $id, 'type' => $type, 'delta' => $delta, 'at' => $at, 'who' => $who, 'note' => $note,
                'session_id' => $session, 'session_date' => $session ? '2026-09-21' : null, 'session_title' => $session ? 'Individual' : null];
    }

    public function testSaldoPasoAPasoUneClaseYDescuentoYDetectaDescuadre(): void
    {
        // Caso real anonimizado: 3 clases descontadas tarde (08/10) de un bono de 4 que hoy tiene 0
        $consumos = [];
        foreach ([[1, '2026-09-21'], [2, '2026-09-28'], [3, '2026-10-05']] as [$sid, $date]) {
            $consumos[] = ['session_id' => $sid, 'date' => $date, 'at' => $date . ' 20:00:00', 'title' => 'Individual', 'state' => 'Presente', 'deducted_at' => '2026-10-08 17:03:00'];
        }
        $moves = [
            $this->move(11, L::DEDUCTED, -1, '2026-10-08 17:03:18', 1),
            $this->move(12, L::DEDUCTED, -1, '2026-10-08 17:03:49', 2),
            $this->move(13, L::DEDUCTED, -1, '2026-10-08 17:06:25', 3),
            $this->move(14, L::EXPIRY_ALERT, 0, '2026-10-07 01:11:28', null, 'Aviso: caduca el 14/10', H::SYSTEM),
        ];
        $l = H::buildBonoLedger($this->bono(), $consumos, $moves);

        // Compra primero, luego por fecha: las clases en su día (no el día del descuento)
        $this->assertSame(['emitido', 'consumo', 'consumo', 'consumo', L::EXPIRY_ALERT], array_column($l['events'], 'kind'));
        $this->assertSame([4, 3, 2, 1, 1], array_column($l['events'], 'saldo'));
        $this->assertSame(1, $l['expected']);
        $this->assertSame(1, $l['diff']);                       // falta 1 sesión por explicar
        $this->assertEqualsCanonicalizing([11, 12, 13, 14], $l['absorbed']);
        $this->assertStringContainsString('el 08/10/2026 por Valèria · 17 días después de la clase', $l['events'][1]['text']);
        $this->assertStringContainsString('caduca el 14/10', $l['events'][4]['text']);
    }

    public function testDevolucionSinDescuentoPrevioSeDeduce(): void
    {
        // Se descontó antes de existir el libro y luego se devolvió: no debe inventar una sesión de más
        $moves = [$this->move(20, L::REFUNDED, 1, '2026-10-07 10:00:00', 5)];
        $l = H::buildBonoLedger($this->bono(['sessions_remaining' => 4]), [], $moves);
        $this->assertSame(0, $l['diff']);
        $this->assertTrue(!empty($l['events'][1]['inferred']));
    }

    public function testDescuentoAutomaticoYAnulacion(): void
    {
        $consumos = [['session_id' => 7, 'date' => '2026-10-09', 'at' => '2026-10-09 18:00:00', 'title' => 'Grupo', 'state' => 'Falta sin justificar', 'deducted_at' => '2026-10-09 19:00:00']];
        $moves = [
            $this->move(30, L::DEDUCTED, -1, '2026-10-09 19:00:00', 7, 'Automático: Falta sin justificar'),
            $this->move(31, L::VOIDED, -3, '2026-10-10 09:00:00', null, 'Motivo: error'),
        ];
        $l = H::buildBonoLedger($this->bono(['sessions_remaining' => 0]), $consumos, $moves);
        $this->assertSame(0, $l['diff']);
        $this->assertTrue($l['has_void_move']);
        $this->assertStringContainsString('automáticamente (Falta sin justificar)', $l['events'][1]['text']);
    }

    public function testRepartoDeProximasClasesYSobrante(): void
    {
        $bonos = [
            ['id' => 2, 'remaining' => 3, 'start_date' => '2026-10-05', 'expires_at' => '2026-10-26'],
            ['id' => 1, 'remaining' => 1, 'start_date' => '2026-09-14', 'expires_at' => '2026-10-14'],
        ];
        $plan = H::planUpcoming($bonos, [
            ['session_id' => 100, 'date' => '2026-10-12'],   // del que caduca antes (#1)
            ['session_id' => 101, 'date' => '2026-10-19'],   // #1 ya caducado → #2
            ['session_id' => 102, 'date' => '2026-11-02'],   // ninguno vale
        ]);
        $this->assertSame([100 => 1, 101 => 2, 102 => null], $plan['assign']);
        $this->assertSame([1 => 0, 2 => 2], $plan['leftover']);
    }

    public function testAvisosAgrupadosYPosibleDobleDescuento(): void
    {
        $cards = [
            ['id' => 2, 'label' => 'Bono #2', 'status' => 'Vigente', 'diff' => 1, 'expected' => 4, 'real' => 3, 'leftover' => 1,
             'created_at' => '2026-10-05 19:58:00', 'start_date' => '2026-10-05', 'expires_at' => '2026-10-26'],
            ['id' => 1, 'label' => 'Bono #1', 'status' => 'Agotado', 'diff' => 0, 'expected' => 0, 'real' => 0, 'leftover' => 0,
             'created_at' => '2026-09-14 17:43:00', 'start_date' => '2026-09-14', 'expires_at' => '2026-10-14'],
        ];
        // La clase del 05/10 a las 20:00 se descontó del #1 tres días después
        $consumos = [3 => ['bono_id' => 1, 'at' => '2026-10-05 20:00:00', 'days_late' => 3, 'deducted_at' => '2026-10-08 17:06:00']];
        $upcoming = [
            ['at' => '2026-11-02 20:00:00', 'bono_id' => null, 'declined' => false, 'link' => 'clases/9'],
            ['at' => '2026-11-09 20:00:00', 'bono_id' => null, 'declined' => false, 'link' => 'clases/10'],
        ];
        $alerts = H::buildAlerts($cards, $consumos, [], $upcoming, 0, '2026-10-06 15:53:39', 1125);

        $this->assertSame(['danger', 'warn', 'info'], array_column($alerts, 'level'));
        $this->assertStringContainsString('le falta 1 sesión por explicar', $alerts[0]['text']);
        $this->assertStringContainsString('Posible doble descuento: la clase del 05/10/2026 se descontó del Bono #1 el 08/10/2026', $alerts[0]['text']);
        $this->assertStringContainsString('2 clases programadas', $alerts[1]['text']);   // una sola alerta para todas
        $this->assertStringContainsString('le sobrará 1 sesión del Bono #2', $alerts[2]['text']);
    }
}
