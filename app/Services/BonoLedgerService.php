<?php

namespace App\Services;

/**
 * TICKET-013 — Libro de movimientos del bono (`bono_movements`).
 *
 * Registro SOLO de añadir: cada cosa que le pasa al bono de un alumno deja
 * una fila (quién, cuándo, de qué sesión/bono). Nunca se edita ni se borra.
 * Registrar NUNCA debe tumbar la operación de negocio: si la tabla no existe
 * (entorno sin la migración) o falla el insert, se anota en el log y se sigue.
 */
class BonoLedgerService
{
    public const GRANTED       = 'granted';        // alta de bono (+sesiones)
    public const DEDUCTED      = 'deducted';       // sesión descontada (-1)
    public const REFUNDED      = 'refunded';       // sesión devuelta (+1)
    public const DEBT_SETTLED  = 'debt_settled';   // deuda saldada con un bono
    public const DEBT_RESOLVED = 'debt_resolved';  // deuda resuelta sin bono (pagada fuera / condonada)
    public const EXTENDED      = 'extended';       // caducidad ampliada
    public const ADJUSTED      = 'adjusted';       // edición manual de saldo/fecha
    public const EXPIRY_ALERT  = 'expiry_alert';   // aviso de caducidad enviado
    public const ASSIGNED      = 'assigned';       // bono sin dueño asignado a un alumno

    private const LABELS = [
        self::GRANTED       => ['Bono emitido',          'bi-ticket-perforated-fill', 'ok'],
        self::DEDUCTED      => ['Sesión descontada',     'bi-dash-circle-fill',       'neutral'],
        self::REFUNDED      => ['Sesión devuelta',       'bi-arrow-counterclockwise', 'ok'],
        self::DEBT_SETTLED  => ['Deuda saldada con bono', 'bi-check-circle-fill',     'ok'],
        self::DEBT_RESOLVED => ['Deuda resuelta',        'bi-check2-square',          'ok'],
        self::EXTENDED      => ['Caducidad ampliada',    'bi-calendar-plus-fill',     'risk'],
        self::ADJUSTED      => ['Ajuste manual',         'bi-pencil-fill',            'neutral'],
        self::EXPIRY_ALERT  => ['Aviso de caducidad',    'bi-hourglass-split',        'risk'],
        self::ASSIGNED      => ['Bono asignado',         'bi-person-check-fill',      'ok'],
    ];

    /** @return array{0:string,1:string,2:string} [etiqueta, icono, tono] */
    public static function label(string $type): array
    {
        return self::LABELS[$type] ?? [$type, 'bi-dot', 'neutral'];
    }

    /** Usuario de la sesión actual (null en CLI / sin sesión). */
    private static function actor(): ?int
    {
        try {
            $id = session('id');
            return $id ? (int) $id : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Anota un movimiento. Nunca lanza.
     *
     * @param int         $delta variación de sesiones del bono (+/-); 0 si no cambia el saldo
     * @param int|null    $actor null = usuario de la sesión actual
     */
    public static function log(int $playerId, string $type, int $delta = 0, ?int $bonoId = null, ?int $sessionId = null, ?string $note = null, ?int $actor = null): void
    {
        if ($playerId <= 0) {
            return;
        }
        try {
            \Config\Database::connect()->table('bono_movements')->insert([
                'player_id'  => $playerId,
                'bono_id'    => $bonoId ?: null,
                'session_id' => $sessionId ?: null,
                'type'       => $type,
                'delta'      => $delta,
                'note'       => $note !== null ? mb_substr($note, 0, 255) : null,
                'actor_id'   => $actor ?? self::actor(),
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable $e) {
            log_message('error', 'BonoLedgerService::log falló (' . $type . '): ' . $e->getMessage());
        }
    }

    /**
     * Movimientos de un alumno, del más reciente al más antiguo.
     *
     * @return array<int,array>
     */
    public function forPlayer(int $playerId, int $limit = 100): array
    {
        try {
            return \Config\Database::connect()->table('bono_movements bm')
                ->select('bm.*, u.name AS actor_name, cs.title AS session_title, cs.session_date')
                ->join('users u', 'u.id = bm.actor_id', 'left')
                ->join('class_sessions cs', 'cs.id = bm.session_id', 'left')
                ->where('bm.player_id', $playerId)
                ->orderBy('bm.id', 'DESC')
                ->limit($limit)
                ->get()->getResultArray();
        } catch (\Throwable $e) {
            log_message('error', 'BonoLedgerService::forPlayer falló: ' . $e->getMessage());
            return [];
        }
    }
}
