<?php

use App\Libraries\Push\DeliveryPolicy as P;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * Regla de borrado de suscripciones push según la respuesta del servicio push.
 * Es la protección contra el peor fallo posible: vaciar push_subscriptions por un
 * error de configuración o una caída de FCM.
 */
final class DeliveryPolicyTest extends CIUnitTestCase
{
    /** @dataProvider classification */
    public function testClassify(int $code, string $expected): void
    {
        $this->assertSame($expected, P::classify($code));
    }

    public static function classification(): array
    {
        return [
            '200'  => [200, P::OK],
            '201'  => [201, P::OK],
            '204'  => [204, P::OK],
            '404'  => [404, P::GONE],
            '410'  => [410, P::GONE],
            'red'  => [0, P::TRANSIENT],
            '408'  => [408, P::TRANSIENT],
            '429'  => [429, P::TRANSIENT],
            '500'  => [500, P::TRANSIENT],
            '503'  => [503, P::TRANSIENT],
            '400'  => [400, P::REJECTED],
            '401'  => [401, P::REJECTED],
            '403'  => [403, P::REJECTED],
            '413'  => [413, P::REJECTED],
            '301'  => [301, P::REJECTED],
        ];
    }

    public function testSuccessResetsAndGoneDeletes(): void
    {
        $plan = P::plan([201, 410, 404], [3, 0, 0]);
        $this->assertSame([P::ACTION_RESET, P::ACTION_DELETE, P::ACTION_DELETE], $plan['actions']);
        $this->assertFalse($plan['config_suspected']);
    }

    public function testTransientFailuresNeverCountNorDelete(): void
    {
        // Aunque la suscripción ya acumule 4 fallos, una caída de red/FCM no la borra.
        $plan = P::plan([0, 429, 503, 500], [4, 4, 4, 4]);
        $this->assertSame(array_fill(0, 4, P::ACTION_KEEP), $plan['actions']);
    }

    public function testRejectionCountsAndDeletesOnTheFifth(): void
    {
        $this->assertSame([P::ACTION_INCREMENT], P::plan([400], [0])['actions']);
        $this->assertSame([P::ACTION_INCREMENT], P::plan([400], [3])['actions']);
        $this->assertSame([P::ACTION_DELETE], P::plan([400], [4])['actions']);
    }

    public function testWholeBatchOf401Or403WithoutSuccessIsAConfigurationProblem(): void
    {
        $plan = P::plan([403, 403, 401], [4, 4, 4]);
        $this->assertTrue($plan['config_suspected']);
        $this->assertSame(array_fill(0, 3, P::ACTION_KEEP), $plan['actions'], 'No se borra nada aunque estén al límite');
    }

    public function testASingleSuccessMeansItIsNotAConfigurationProblem(): void
    {
        $plan = P::plan([201, 403], [0, 4]);
        $this->assertFalse($plan['config_suspected']);
        $this->assertSame([P::ACTION_RESET, P::ACTION_DELETE], $plan['actions']);
    }

    public function testMixedRejectionCodesAreNotAConfigurationProblem(): void
    {
        $this->assertFalse(P::plan([403, 400], [0, 0])['config_suspected']);
    }

    public function testASingleSubscriptionCannotBeDiagnosedAsConfiguration(): void
    {
        $plan = P::plan([403], [4]);
        $this->assertFalse($plan['config_suspected']);
        $this->assertSame([P::ACTION_DELETE], $plan['actions']);
    }

    public function testEmptyBatch(): void
    {
        $this->assertSame(['actions' => [], 'config_suspected' => false], P::plan([], []));
    }
}
