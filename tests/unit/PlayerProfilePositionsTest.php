<?php

use CodeIgniter\Test\CIUnitTestCase;
use App\Models\PlayerProfileModel;

/**
 * Un alumno puede tener varias posiciones (p. ej. "Extremo" y
 * "Mediapunta"). Antes era un único texto libre — el propio origen del
 * desbordamiento reportado en TICKET-002 ("Extremo/mediapunta" como
 * una sola cadena larga). Ahora se guarda como lista y se muestra como
 * tal en la ficha.
 */
final class PlayerProfilePositionsTest extends CIUnitTestCase
{
    public function testEncodeGuardaComoJson(): void
    {
        $encoded = PlayerProfileModel::encodePositions(['extremo_derecho', 'mediapunta']);
        $this->assertSame(['extremo_derecho', 'mediapunta'], json_decode($encoded, true));
    }

    public function testEncodeVacioDevuelveNull(): void
    {
        $this->assertNull(PlayerProfileModel::encodePositions([]));
        $this->assertNull(PlayerProfileModel::encodePositions(['', '  ']));
    }

    public function testEncodeEliminaDuplicadosYEspacios(): void
    {
        $encoded = PlayerProfileModel::encodePositions([' portero ', 'portero', 'central']);
        $this->assertSame(['portero', 'central'], json_decode($encoded, true));
    }

    public function testDecodeJsonNuevo(): void
    {
        $raw = json_encode(['extremo_derecho', 'mediapunta']);
        $this->assertSame(['extremo_derecho', 'mediapunta'], PlayerProfileModel::decodePositions($raw));
    }

    public function testDecodeTextoLibreLegacyConBarra(): void
    {
        // Caso real que causaba el desbordamiento (TICKET-002)
        $this->assertSame(['Extremo', 'mediapunta'], PlayerProfileModel::decodePositions('Extremo/mediapunta'));
    }

    public function testDecodeTextoLibreLegacySimple(): void
    {
        $this->assertSame(['Portero'], PlayerProfileModel::decodePositions('Portero'));
    }

    public function testDecodeNuloOVacioDevuelveArrayVacio(): void
    {
        $this->assertSame([], PlayerProfileModel::decodePositions(null));
        $this->assertSame([], PlayerProfileModel::decodePositions(''));
        $this->assertSame([], PlayerProfileModel::decodePositions('   '));
    }

    public function testPositionLabelUsaElCatalogo(): void
    {
        $this->assertSame('Mediapunta', PlayerProfileModel::positionLabel('mediapunta'));
    }

    public function testPositionLabelConservaTextoLibreDesconocido(): void
    {
        $this->assertSame('Portero suplente', PlayerProfileModel::positionLabel('Portero suplente'));
    }

    public function testDecodePositionLabelsTraduceClavesDelCatalogo(): void
    {
        $raw = json_encode(['extremo_derecho', 'mediapunta']);
        $this->assertSame(['Extremo derecho', 'Mediapunta'], PlayerProfileModel::decodePositionLabels($raw));
    }

    public function testRoundTripEncodeDecode(): void
    {
        $original = ['central', 'pivote'];
        $decoded  = PlayerProfileModel::decodePositions(PlayerProfileModel::encodePositions($original));
        $this->assertSame($original, $decoded);
    }

    public function testFormatPositionsUneConBarra(): void
    {
        $raw = json_encode(['extremo_derecho', 'mediapunta']);
        $this->assertSame('Extremo derecho / Mediapunta', PlayerProfileModel::formatPositions($raw));
        // el legacy "mediapunta" coincide con la clave del catálogo -> "Mediapunta"
        $this->assertSame('Extremo / Mediapunta', PlayerProfileModel::formatPositions('Extremo/mediapunta'));
        $this->assertSame('—', PlayerProfileModel::formatPositions(null));
        $this->assertSame('sin datos', PlayerProfileModel::formatPositions('', 'sin datos'));
    }

    // ── Cableado en las vistas ──────────────────────────────────────

    public function testFormulariosUsanElPartialDeCheckboxes(): void
    {
        foreach (['create.php', 'edit.php', 'create_profile.php'] as $f) {
            $src = file_get_contents(APPPATH . 'Views/alumnos/' . $f);
            $this->assertStringContainsString(
                "include('alumnos/_position_checkboxes')",
                $src,
                "$f debe usar el selector múltiple de posiciones"
            );
        }
    }

    public function testFichaDeAlumnoMuestraLasPosicionesSeparadasPorBarra(): void
    {
        foreach (['show.php', 'profile.php'] as $f) {
            $src = file_get_contents(APPPATH . 'Views/alumnos/' . $f);
            $this->assertStringContainsString('decodePositionLabels', $src);
            $this->assertStringContainsString("implode(' / ', \$positionLabels)", $src);
            // ya no se pinta como lista <ul><li>
            $this->assertStringNotContainsString('<ul class="metric-value"', $src);
        }
    }
}
