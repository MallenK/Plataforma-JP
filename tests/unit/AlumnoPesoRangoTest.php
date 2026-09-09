<?php

use CodeIgniter\Test\CIUnitTestCase;

/**
 * El campo "Peso (kg)" debe admitir de 10 a 300 kg. El límite anterior
 * (min 30) impedía dar de alta a alumnos de las categorías pequeñas.
 *
 * @internal
 */
final class AlumnoPesoRangoTest extends CIUnitTestCase
{
    private function file(string $rel): string
    {
        return file_get_contents(APPPATH . $rel);
    }

    /** @dataProvider vistasConPeso */
    public function testRangoDePeso(string $vista): void
    {
        $v = $this->file("Views/alumnos/{$vista}");

        $pos = strpos($v, 'name="weight"');
        $this->assertNotFalse($pos, "la vista {$vista} debe tener el input de peso");

        $input = substr($v, $pos, 200);
        $this->assertStringContainsString('min="10"', $input, "{$vista}: el mínimo debe ser 10");
        $this->assertStringContainsString('max="300"', $input, "{$vista}: el máximo debe ser 300");
        $this->assertStringNotContainsString('min="30"', $input, "{$vista}: no debe quedar el mínimo antiguo");
    }

    public static function vistasConPeso(): array
    {
        return [
            'alta de alumno'   => ['create.php'],
            'edición de alumno' => ['edit.php'],
            'ficha propia'     => ['create_profile.php'],
        ];
    }
}
