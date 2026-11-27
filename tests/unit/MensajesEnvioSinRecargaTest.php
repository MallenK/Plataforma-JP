<?php

use CodeIgniter\Test\CIUnitTestCase;

/**
 * Firefox envía de verdad el formulario si se le lanza un evento `submit`
 * artificial (no cancelable): la página se recargaba y el mensaje se perdía.
 */
final class MensajesEnvioSinRecargaTest extends CIUnitTestCase
{
    public function testEnterEnviaConRequestSubmit(): void
    {
        $view = file_get_contents(APPPATH . 'Views/mensajes/index.php');

        $this->assertStringNotContainsString("dispatchEvent(new Event('submit'));", $view);
        $this->assertStringContainsString("document.getElementById('form-message').requestSubmit();", $view);
    }
}
