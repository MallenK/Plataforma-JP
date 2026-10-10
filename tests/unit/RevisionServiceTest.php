<?php

use App\Services\RevisionService as R;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * «Pendiente de revisar» (v1.33.0) — partes puras: valor de las sesiones
 * sin usar y lectura de importes en euros escritos por el admin.
 */
final class RevisionServiceTest extends CIUnitTestCase
{
    public function testValorSinUsarProporcional(): void
    {
        $this->assertSame(9000, R::unusedValueCents(22500, 2, 5));   // 2 de 5 sesiones de 225 €
        $this->assertSame(3333, R::unusedValueCents(10000, 1, 3));   // redondeo al céntimo
    }

    public function testValorSinUsarCasosLimite(): void
    {
        $this->assertSame(0, R::unusedValueCents(null, 2, 5));
        $this->assertSame(0, R::unusedValueCents(22500, 0, 5));
        $this->assertSame(0, R::unusedValueCents(22500, 2, 0));
    }

    public function testImportesEnEuros(): void
    {
        $this->assertSame(22500, R::parseEuroToCents('225'));
        $this->assertSame(22550, R::parseEuroToCents('225,50'));
        $this->assertSame(22550, R::parseEuroToCents('225.5'));
        $this->assertSame(122500, R::parseEuroToCents('1.225,00 €'));
        $this->assertSame(0, R::parseEuroToCents('0'));
    }

    public function testImportesInvalidos(): void
    {
        $this->assertNull(R::parseEuroToCents(''));
        $this->assertNull(R::parseEuroToCents(null));
        $this->assertNull(R::parseEuroToCents('-5'));
        $this->assertNull(R::parseEuroToCents('abc'));
        $this->assertNull(R::parseEuroToCents('12,345'));
    }
}
