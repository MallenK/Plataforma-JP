<?php

use App\Services\ExternosService;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * Externos (clase de prueba / suelta): reglas puras de validación, edad, teléfono y permisos.
 *
 * @internal
 */
final class ExternosServiceTest extends CIUnitTestCase
{
    private const TODAY = '2026-11-09';

    private function ok(array $over = []): array
    {
        return array_merge([
            'first_name' => 'Leo', 'last_name' => 'Martí', 'birth_date' => '2010-05-01',
            'phone' => '600 111 222', 'guardian_name' => 'Ana', 'guardian_phone' => '600111222',
            'kind' => 'trial',
        ], $over);
    }

    public function testPhoneKeyQuitaPrefijoYSeparadores(): void
    {
        $this->assertSame('600111222', ExternosService::phoneKey('+34 600 111 222'));
        $this->assertSame('600111222', ExternosService::phoneKey('0034600111222'));
        $this->assertSame('600111222', ExternosService::phoneKey('600-111-222'));
        $this->assertNull(ExternosService::phoneKey('  '));
        $this->assertNull(ExternosService::phoneKey(null));
    }

    public function testEdadCumplidaYFechasInvalidas(): void
    {
        $this->assertSame(16, ExternosService::ageOn('2010-05-01', self::TODAY));
        $this->assertSame(16, ExternosService::ageOn('2009-11-10', self::TODAY)); // cumple 17 mañana
        $this->assertSame(17, ExternosService::ageOn('2008-11-10', self::TODAY)); // cumple 18 mañana: aún menor
        $this->assertSame(18, ExternosService::ageOn('2008-11-09', self::TODAY)); // los cumple hoy: ya mayor
        $this->assertNull(ExternosService::ageOn('2030-01-01', self::TODAY));      // futura
        $this->assertNull(ExternosService::ageOn('no-es-fecha', self::TODAY));
        $this->assertNull(ExternosService::ageOn(null, self::TODAY));
    }

    public function testAltaCorrectaDeMenorConTutor(): void
    {
        [$clean, $errors] = ExternosService::validate($this->ok(), self::TODAY);
        $this->assertSame([], $errors);
        $this->assertSame('600111222', $clean['phone_key']);
        $this->assertSame('single', $clean['kind']);
    }

    public function testSoloElNombreEsObligatorio(): void
    {
        [, $e] = ExternosService::validate(['first_name' => ' '], self::TODAY);
        $this->assertSame(['Indica el nombre.'], $e);

        // Sin nacimiento ni contacto: válido (ya no se exigen).
        [$clean, $e2] = ExternosService::validate(['first_name' => 'Leo'], self::TODAY);
        $this->assertSame([], $e2);
        $this->assertNull($clean['birth_date']);
        $this->assertNull($clean['phone_key']);

        // Si se escribe una fecha, tiene que ser válida.
        [, $e3] = ExternosService::validate(['first_name' => 'Leo', 'birth_date' => '31-02-2010'], self::TODAY);
        $this->assertNotEmpty(array_filter($e3, fn($m) => str_contains($m, 'nacimiento')));
    }

    public function testVariasPersonasDeUnFormulario(): void
    {
        $post = ['externos' => [
            ['first_name' => 'Leo', 'method_id' => '1'],
            ['first_name' => '', 'last_name' => '', 'method_id' => '1'],   // fila vacía: se descarta
            ['first_name' => 'Ana', 'price' => '20'],
        ]];
        $people = ExternosService::peopleFromPost($post);
        $this->assertCount(2, $people);
        $this->assertSame('Ana', $people[1]['first_name']);

        // Compatibilidad con el formato antiguo de una sola persona.
        $this->assertCount(1, ExternosService::peopleFromPost(['externo' => ['first_name' => 'Leo']]));
        $this->assertSame([], ExternosService::peopleFromPost([]));
    }

    public function testMenorExigeTutorCompleto(): void
    {
        [, $e] = ExternosService::validate($this->ok(['guardian_name' => '', 'guardian_phone' => '']), self::TODAY);
        $this->assertNotEmpty(array_filter($e, fn($m) => str_contains($m, 'menor de edad')));

        [, $e2] = ExternosService::validate($this->ok(['guardian_phone' => '']), self::TODAY);
        $this->assertNotEmpty(array_filter($e2, fn($m) => str_contains($m, 'menor de edad')));
    }

    public function testAdultoNoNecesitaTutor(): void
    {
        [, $e] = ExternosService::validate($this->ok([
            'birth_date' => '1995-01-01', 'guardian_name' => '', 'guardian_phone' => '',
        ]), self::TODAY);
        $this->assertSame([], $e);
    }

    public function testContactoYaNoEsObligatorio(): void
    {
        [, $e] = ExternosService::validate($this->ok([
            'birth_date' => '1995-01-01', 'phone' => '', 'email' => '', 'guardian_phone' => '',
        ]), self::TODAY);
        $this->assertSame([], $e);
    }

    public function testEmailInvalidoYTipoSiempreSingle(): void
    {
        [$clean, $e] = ExternosService::validate($this->ok(['email' => 'no-es-email', 'kind' => 'hack']), self::TODAY);
        $this->assertNotEmpty(array_filter($e, fn($m) => str_contains($m, 'email')));
        $this->assertSame('single', $clean['kind']); // el tipo ya no se pregunta ni se acepta del navegador
    }

    public function testEstadoDelDineroDeUnaClaseDeExterno(): void
    {
        $this->assertSame('sin_precio', ExternosService::moneyState(null, 0));
        $this->assertSame('gratis', ExternosService::moneyState(0, 0));
        $this->assertSame('pendiente', ExternosService::moneyState(2500, 0));
        $this->assertSame('parcial', ExternosService::moneyState(2500, 1000));
        $this->assertSame('pagado', ExternosService::moneyState(2500, 2500));
        $this->assertSame('pagado', ExternosService::moneyState(2500, 3000));
    }

    public function testNuncaSeDescartaNadaEnSilencio(): void
    {
        $svc = new ExternosService();
        $never = function () { $this->fail('no debe crearse la clase'); };

        // Alumnos registrados Y persona externa a la vez → error, no se elige uno por el usuario.
        $r = $svc->createClass($never, [
            'type' => 'single', 'externo_mode' => '1', 'player_ids' => ['5'],
            'externos' => [['first_name' => 'Leo']],
        ], 1, false);
        $this->assertFalse($r['success']);
        $this->assertStringContainsString('a la vez', $r['error']);

        // Personas externas rellenadas pero modo «alumno registrado» → error.
        $r2 = $svc->createClass($never, [
            'type' => 'single', 'externo_mode' => '0', 'externos' => [['first_name' => 'Leo']],
        ], 1, false);
        $this->assertFalse($r2['success']);
        $this->assertStringContainsString('no se guarda nada por suposición', $r2['error']);

        // Clase recurrente con externos → error.
        $r3 = $svc->createClass($never, [
            'type' => 'recurring', 'externo_mode' => '1', 'externos' => [['first_name' => 'Leo']],
        ], 1, false);
        $this->assertFalse($r3['success']);
    }

    public function testEditarExternoYFechasEnTodaLaApp(): void
    {
        $routes = file_get_contents(APPPATH . 'Config/Routes.php');
        $this->assertMatchesRegularExpression("#externos/\(:num\)/editar'.{0,120}role:superadmin,admin,staff,coach#s", $routes, 'la ruta de editar debe tener filtro de rol');

        // El selector de fechas DD/MM/AAAA se carga desde el layout base (afecta a TODAS las pantallas).
        $this->assertStringContainsString('assets/js/date-es.js', file_get_contents(APPPATH . 'Views/layouts/base.php'));

        // La lista de personas se carga donde se crean clases (formulario completo, calendario, dashboard).
        foreach (['clases/create.php', 'clases/index.php', 'dashboard/index.php'] as $v) {
            $this->assertStringContainsString('externos-list.js', file_get_contents(APPPATH . 'Views/' . $v), $v);
        }
    }

    public function testQuitarYEditarSeGuiaAlUsuario(): void
    {
        $view = file_get_contents(APPPATH . 'Views/clases/_externos.php');
        $this->assertStringContainsString('/editar', $view, 'la ficha debe permitir editar');
        $this->assertStringContainsString('has_payments', $view, 'el botón de quitar depende de que no haya cobros');
        $this->assertStringContainsString('Guardar y añadir otra', $view);
    }

    public function testPrecioYCobradoAhoraDelFormulario(): void
    {
        $m = ExternosService::parseMoney(['price' => '25,50', 'paid_now' => '1', 'method_id' => '2']);
        $this->assertSame(2550, $m['price']);
        $this->assertSame(2550, $m['pay_now']);          // «cobrado ahora» = el importe completo
        $this->assertSame(2, $m['method_id']);
        $this->assertSame([], $m['errors']);

        $this->assertSame(0, ExternosService::parseMoney(['price' => '0'])['price']);   // gratis
        $this->assertNull(ExternosService::parseMoney(['price' => ''])['price']);       // sin fijar

        $bad = ExternosService::parseMoney(['price' => 'abc']);
        $this->assertNotEmpty($bad['errors']);

        // No se puede marcar «cobrado ahora» sin un precio mayor que 0
        $this->assertNotEmpty(ExternosService::parseMoney(['price' => '', 'paid_now' => '1'])['errors']);
        $this->assertNotEmpty(ExternosService::parseMoney(['price' => '0', 'paid_now' => '1'])['errors']);
    }

    public function testSumaDeCobrosPorMedioDeLosAlumnosYDeLosExternos(): void
    {
        $merged = \App\Services\FinanceReportService::mergeByMethod(
            [['name' => 'Efectivo', 'v' => 1000], ['name' => 'Bizum', 'v' => 500]],
            [['name' => 'Efectivo', 'v' => 2500], ['name' => 'Tarjeta', 'v' => 700]]
        );
        $this->assertSame([['name' => 'Efectivo', 'v' => 3500], ['name' => 'Tarjeta', 'v' => 700], ['name' => 'Bizum', 'v' => 500]], $merged);
    }

    public function testRutasDeDineroDelExternoExigenAccesoTotalAFinanzas(): void
    {
        $routes = file_get_contents(APPPATH . 'Config/Routes.php');
        foreach (['externos/(:num)/precio', 'externos/(:num)/cobro', 'externos/cobros/(:num)/anular'] as $path) {
            $pos = strpos($routes, $path);
            $this->assertNotFalse($pos, "ruta $path");
            $this->assertStringContainsString("'finance:full'", substr($routes, $pos, 220), "la ruta $path debe llevar finance:full");
        }
    }

    public function testClaseConExternoNoNecesitaAlumnoRegistrado(): void
    {
        $svc = \App\Services\ClasesService::class;
        // Sin externo: sigue exigiendo entrenador Y alumno (regla de siempre).
        $this->assertNotNull($svc::participantsError([7], []));
        $this->assertNotNull($svc::participantsError([], [3]));
        $this->assertNull($svc::participantsError([7], [3]));
        // Con externo: el externo ocupa el lugar del alumno, pero el entrenador sigue siendo obligatorio.
        $this->assertNull($svc::participantsError([7], [], true));
        $this->assertNotNull($svc::participantsError([], [], true));
    }

    public function testClaseSoloConExternoNoSeConsideraVaciaEnRevision(): void
    {
        $src = file_get_contents(APPPATH . 'Services/RevisionService.php');
        $this->assertGreaterThanOrEqual(2, substr_count($src, 'class_session_guests'), 'incompleteSessions y counts deben excluir clases con externo');
        $this->assertStringContainsString('class_session_guests', file_get_contents(APPPATH . 'Services/ClasesService.php'));
    }

    public function testPermisosPorRol(): void
    {
        $session = ['coaches' => [['user_id' => 7], ['user_id' => 9]]];

        $this->assertTrue(ExternosService::canManage($session, 1, 'admin'));
        $this->assertTrue(ExternosService::canManage($session, 1, 'superadmin'));
        $this->assertTrue(ExternosService::canManage($session, 7, 'coach'));
        $this->assertFalse(ExternosService::canManage($session, 8, 'coach'));   // coach ajeno
        $this->assertFalse(ExternosService::canManage($session, 7, 'player'));  // alumno nunca
        $this->assertFalse(ExternosService::canManage($session, null, 'coach'));

        $this->assertTrue(ExternosService::canSeePersonal('admin'));
        $this->assertFalse(ExternosService::canSeePersonal('coach'));
        $this->assertFalse(ExternosService::canSeePersonal('staff'));
    }
}
