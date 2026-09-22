<?php

use CodeIgniter\Test\CIUnitTestCase;

/**
 * Fija por escaneo de código el arreglo del bug real investigado 2026-09-22:
 * un admin no podía responder en un chat largo (varios adjuntos de vídeo)
 * porque la sesión caducaba (`sec_session_timeout`, 10 min de inactividad)
 * mientras leía/veía los adjuntos, y el fallo (401 + session_expired) no
 * dejaba ningún rastro en BD ni en logs — era indistinguible de "no ha
 * pasado nada".
 *
 * El arreglo tiene dos partes:
 *  1. AuthFilter registra en log cuándo y por qué caduca una sesión.
 *  2. Mensajes ya no manda al usuario a /login perdiendo el mensaje escrito:
 *     abre un modal de reautenticación rápida y reintenta automáticamente
 *     la acción que falló (abrir conversación, enviar, sondeo).
 */
final class MensajesSesionCaducadaTest extends CIUnitTestCase
{
    private function src(string $rel): string
    {
        return file_get_contents(APPPATH . $rel);
    }

    public function testAuthFilterRegistraLaSesionCaducada(): void
    {
        $filter = $this->src('Filters/AuthFilter.php');
        $this->assertStringContainsString("log_message('info'", $filter);
        $this->assertStringContainsString('sesión caducada', $filter);
        // El uid y el path se capturan ANTES de session()->destroy(), si no
        // siempre se loguearía uid=null.
        $this->assertMatchesRegularExpression(
            '/\$uid\s*=\s*session\(\)->get\(\'id\'\);.*?session\(\)->destroy\(\);/s',
            $filter
        );
    }

    public function testMensajesTieneModalDeReautenticacion(): void
    {
        $view = $this->src('Views/mensajes/index.php');
        $this->assertStringContainsString('id="modalReauth"', $view);
        $this->assertStringContainsString('id="form-reauth"', $view);
        $this->assertStringContainsString('id="reauth-password"', $view);
    }

    public function testYaNoExisteElRedirectSilenciosoAlLogin(): void
    {
        // Antes: showSessionExpiredToast() mandaba a /login?expired=1 y el
        // usuario perdía el mensaje que estaba escribiendo. Ahora todos los
        // puntos que detectan sesión caducada pasan por el modal.
        $view = $this->src('Views/mensajes/index.php');
        $this->assertStringNotContainsString('showSessionExpiredToast', $view);
        $this->assertStringContainsString('function openReauthModal', $view);
    }

    public function testLosTresPuntosDeDeteccionUsanElModal(): void
    {
        $view = $this->src('Views/mensajes/index.php');

        // Abrir conversación: reintenta la misma apertura.
        $this->assertStringContainsString(
            "handleFetchError(err, 'Abrir conversación', showChatError, () => openConversation(convId, otherId));",
            $view
        );

        // Enviar mensaje: reintenta el mismo submit (el draft no se limpia
        // en el catch, así que el texto/adjunto siguen en el formulario).
        $this->assertStringContainsString(
            "document.getElementById('form-message').requestSubmit()",
            $view
        );

        // Sondeo (poll de mensajes y de conversaciones): se detecta antes de
        // que el usuario intente escribir.
        $this->assertStringContainsString('openReauthModal(startPolling)', $view);
    }

    public function testElDraftNoSeBorraSiElEnvioFalla(): void
    {
        $view = $this->src('Views/mensajes/index.php');
        // bodyInput.value = '' solo debe ejecutarse en el camino de éxito,
        // nunca en el catch — si no, un reintento tras reautenticar
        // mandaría un mensaje vacío.
        $successPos = strpos($view, "bodyInput.value = '';");
        $catchPos   = strpos($view, "handleFetchError(err, 'Enviar mensaje'");
        $this->assertNotFalse($successPos);
        $this->assertNotFalse($catchPos);
        $this->assertLessThan($catchPos, $successPos, 'El value=\'\' debe estar en el bloque try, antes del catch');
    }

    public function testElModalDeReautenticacionReusaElLoginExistente(): void
    {
        $view = $this->src('Views/mensajes/index.php');
        // Reutiliza POST /login (AuthController::loginPost) tal cual, con
        // rate-limit/lockout ya existentes (AuthGuardService) — no se crea
        // un endpoint de autenticación nuevo.
        $this->assertStringContainsString("BASE + 'login'", $view);
        $this->assertStringContainsString('MY_EMAIL', $view);
    }

    public function testMensajesControllerExponeElEmailActualParaLaVista(): void
    {
        $ctrl = $this->src('Controllers/MensajesController.php');
        $this->assertStringContainsString("'currentEmail'", $ctrl);
    }
}
