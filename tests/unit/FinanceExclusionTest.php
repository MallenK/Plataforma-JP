<?php

use App\Services\FinanceExclusion as FX;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * Usuarios que no cuentan en las finanzas (cuentas de prueba): reglas puras de
 * FinanceExclusion y que TODA consulta de dinero/clases de Finanzas la use (si
 * alguien añade una consulta nueva sin filtro, este test avisa).
 */
final class FinanceExclusionTest extends CIUnitTestCase
{
    protected function tearDown(): void
    {
        FX::reset();
        parent::tearDown();
    }

    // ── parseIds ────────────────────────────────────────────────────

    public function testParseIdsLimpiaRepetidosYBasura(): void
    {
        $this->assertSame([3, 7], FX::parseIds(' 7, 3,x,7,,-2,0 '));
        $this->assertSame([], FX::parseIds(''));
        $this->assertSame([], FX::parseIds(null));
    }

    // ── playerSql / playerCond ──────────────────────────────────────

    public function testSinExcluidosNoSeFiltraNada(): void
    {
        $this->assertSame('', FX::playerSql('p.player_id', []));
        $this->assertSame('', FX::playerCond('p.player_id', []));
        $this->assertSame('', FX::sessionSql('cs', []));
        $this->assertSame('', FX::sessionCond('cs', []));
    }

    public function testPlayerSqlDejaFueraALosExcluidos(): void
    {
        $this->assertSame(' AND p.player_id NOT IN (2,330001)', FX::playerSql('p.player_id', [330001, 2, 2]));
        $this->assertSame('p.player_id NOT IN (2,330001)', FX::playerCond('p.player_id', [330001, 2]));
    }

    public function testLosIdsSeSaneanAEnterosAntesDeIrAlSql(): void
    {
        // Aunque llegara basura, solo salen enteros positivos: no hay inyección posible.
        $sql = FX::playerSql('u.id', ['5; DROP TABLE users', -1, 0, 9]);
        $this->assertSame(' AND u.id NOT IN (5,9)', $sql);
    }

    // ── sessionSql / sessionCond ────────────────────────────────────

    public function testSessionSqlExcluyeClasesConAlumnoOEntrenadorExcluido(): void
    {
        $sql = FX::sessionSql('cs', [2, 8]);
        $this->assertStringStartsWith(' AND NOT EXISTS (SELECT 1 FROM class_session_players', $sql);
        // alumno, entrenador asignado al alumno y entrenador de la clase
        $this->assertStringContainsString('fxp.user_id IN (2,8)', $sql);
        $this->assertStringContainsString('fxp.coach_id IN (2,8)', $sql);
        $this->assertStringContainsString('FROM class_session_coaches fxc', $sql);
        $this->assertStringContainsString('fxc.user_id IN (2,8)', $sql);
        $this->assertStringContainsString('fxp.session_id = cs.id', $sql);
    }

    public function testSessionSqlRespetaElAliasPedido(): void
    {
        $this->assertStringContainsString('fxc.session_id = s.id', FX::sessionSql('s', [1]));
    }

    // ── filterRows ──────────────────────────────────────────────────

    public function testFilterRowsQuitaUsuariosYClasesExcluidos(): void
    {
        $rows = [
            ['user_id' => 1,  'session_id' => 10],   // normal
            ['user_id' => 2,  'session_id' => 11],   // usuario excluido
            ['user_id' => 3,  'session_id' => 12],   // clase excluida
            ['user_id' => 4,  'session_id' => 13],   // normal
        ];
        $out = FX::filterRows($rows, 'user_id', 'session_id', [2], [12]);
        $this->assertSame([10, 13], array_column($out, 'session_id'));
    }

    public function testFilterRowsSinExcluidosDevuelveLoMismo(): void
    {
        $rows = [['user_id' => 1, 'session_id' => 10]];
        $this->assertSame($rows, FX::filterRows($rows, 'user_id', 'session_id', [], []));
    }

    // ── Todas las consultas de Finanzas pasan por el filtro ─────────

    private function src(string $rel): string
    {
        return str_replace("\r\n", "\n", (string) file_get_contents(APPPATH . $rel));
    }

    /**
     * Cada consulta de dinero (fin_charges / fin_payments / player_bonos con
     * importes) y cada consulta de clases de FinanceReportService debe llevar el
     * filtro. Se cuentan los usos: si alguien añade una consulta nueva y olvida
     * el filtro, el número baja de lo esperado respecto a las consultas.
     */
    public function testElInformeDeFinanzasAplicaElFiltroEnTodasSusConsultas(): void
    {
        $src = $this->src('Services/FinanceReportService.php');

        // Consultas sobre dinero o clases (cada una empieza por FROM/JOIN de estas tablas).
        preg_match_all('/FROM\s+(fin_charges|fin_payments|player_bonos|class_sessions|class_session_players)\b/', $src, $m);
        $consultas = count($m[0]);

        preg_match_all('/FinanceExclusion::(playerSql|sessionSql)\(/', $src, $f);
        $filtros = count($f[0]);

        // Consultas que a propósito no filtran: el reparto por medio de pago usa fin_payments p
        // (ya filtrado arriba); los gastos no dependen de ningún alumno.
        $this->assertGreaterThanOrEqual(20, $filtros, 'FinanceReportService debe filtrar a los excluidos en todas sus consultas');
        $this->assertGreaterThanOrEqual($consultas - 4, $filtros,
            "Hay {$consultas} consultas de dinero/clases y solo {$filtros} filtros: ¿una consulta nueva sin FinanceExclusion?");
    }

    public function testRevisionAlumnosYBonosAplicanElFiltro(): void
    {
        $rev = $this->src('Services/RevisionService.php');
        foreach (['unclosedSessions', 'incompleteSessions', 'estimatedPriceBonos', 'expiredUnused', 'counts', 'initialClose'] as $fn) {
            $this->assertMatchesRegularExpression('/function ' . $fn . '\(.*?\n    \}\n/s', $rev, "falta {$fn}");
        }
        $this->assertGreaterThanOrEqual(12, substr_count($rev, 'FinanceExclusion::'), 'RevisionService debe filtrar sesiones, bonos y deudas');
        $this->assertStringContainsString("FinanceExclusion::playerSql('u.id')", $this->src('Services/FinanceService.php'), 'playersOverview');
        $this->assertStringContainsString("FinanceExclusion::playerSql('u.id')", $this->src('Services/BonoReportService.php'), 'informe de bonos');

        $ctl = $this->src('Controllers/FinanzasController.php');
        $this->assertStringContainsString("FinanceExclusion::filterRows(\$control->openDebts(", $ctl, 'lista de deudas de la Revisión');
        $this->assertStringContainsString("FinanceExclusion::playerCond('p.player_id')", $ctl, 'lista de cobros');
    }

    // ── Solo el raíz gestiona la lista ──────────────────────────────

    public function testLaListaSoloLaCambiaElSuperadminRaiz(): void
    {
        $routes = $this->src('Config/Routes.php');
        $this->assertMatchesRegularExpression("#finanzas/configuracion/excluidos'[^;]*'finance:master'#s", $routes);

        $svc = $this->src('Services/FinanceExclusion.php');
        $this->assertStringContainsString('isCurrentMaster()', $svc, 'save() vuelve a comprobar que es el raíz');
        $this->assertStringContainsString("'finance_exclusion'", $svc, 'cada cambio queda en auditoría');
    }

    public function testSiLaListaNoSeLeeNoSeExcluyeANadie(): void
    {
        $svc = $this->src('Services/FinanceExclusion.php');
        $this->assertStringContainsString('no se excluye a nadie', $svc);
    }
}
