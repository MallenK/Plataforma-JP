<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * TICKET-010 — origen de las notificaciones (a dónde llevan al pulsarlas).
 *
 * `notifications.source_type` / `source_id` los usa la campanita para abrir
 * el ticket (/tickets/:id) o la conversación (/mensajes?conv=:id), pero
 * ninguna migración los creaba: en algunas BD se añadieron a mano y en otras
 * pueden no existir. Por eso se añaden solo si faltan.
 *
 * Además rellena el origen de las notificaciones ya enviadas, que se
 * guardaron con NULL porque el modelo descartaba esos campos:
 *  - tickets: por coincidencia EXACTA del título con el nº de ticket
 *    (títulos fijos de TicketsController / MensajesController::reportError);
 *  - mensajes: "Nuevo mensaje de …" → la conversación entre remitente y
 *    destinatario (única por pareja, user1_id = el menor).
 * Nunca enlaza una notificación a algo creado DESPUÉS de ella: un nº de
 * ticket puede reutilizarse si se borran tickets (pasó en local) y el aviso
 * antiguo acabaría abriendo el ticket nuevo.
 * Solo toca filas con source_type NULL → se puede re-ejecutar.
 *
 * SQL equivalente para phpMyAdmin: docs/deploy/migraciones_notificaciones_origen.sql
 */
class AddSourceToNotifications extends Migration
{
    // Comparación en binario: `notifications` y `tickets` no comparten
    // collation en todas las BD (p. ej. 0900_ai_ci vs general_ci) y un
    // `=`/`IN` normal falla con "Illegal mix of collations".
    public const BACKFILL_TICKETS = <<<'SQL'
        UPDATE notifications n
        JOIN tickets t ON CAST(n.title AS BINARY) IN (
            CAST(CONCAT('Nuevo ticket: ', t.ticket_number) AS BINARY),
            CAST(CONCAT('Respuesta a tu ticket ', t.ticket_number) AS BINARY),
            CAST(CONCAT('Ticket ', t.ticket_number, ' actualizado') AS BINARY),
            CAST(CONCAT('Ticket asignado: ', t.ticket_number) AS BINARY),
            CAST(CONCAT('Nota interna en ', t.ticket_number) AS BINARY)
        )
        SET n.source_type = 'ticket', n.source_id = t.id
        WHERE n.source_type IS NULL
          AND n.created_at >= t.created_at
        SQL;

    public const BACKFILL_CONVERSATIONS = <<<'SQL'
        UPDATE notifications n
        JOIN notification_recipients nr ON nr.notification_id = n.id
        JOIN conversations c
          ON c.user1_id = LEAST(n.sender_id, nr.recipient_id)
         AND c.user2_id = GREATEST(n.sender_id, nr.recipient_id)
        SET n.source_type = 'conversation', n.source_id = c.id
        WHERE n.source_type IS NULL
          AND n.type = 'individual'
          AND n.title LIKE 'Nuevo mensaje de %'
          AND (c.created_at IS NULL OR n.created_at >= c.created_at)
        SQL;

    public function up(): void
    {
        // Se consulta antes de alterar nada (CodeIgniter cachea los campos).
        $hasType = $this->db->fieldExists('source_type', 'notifications');
        $hasId   = $this->db->fieldExists('source_id', 'notifications');

        if (!$hasType) {
            $this->db->query(
                "ALTER TABLE `notifications`
                 ADD COLUMN `source_type` ENUM('ticket','conversation') NULL DEFAULT NULL AFTER `file_size`"
            );
        }
        if (!$hasId) {
            $this->db->query(
                "ALTER TABLE `notifications`
                 ADD COLUMN `source_id` INT UNSIGNED NULL DEFAULT NULL AFTER `source_type`"
            );
        }

        $this->db->query(self::BACKFILL_TICKETS);
        $this->db->query(self::BACKFILL_CONVERSATIONS);
    }

    public function down(): void
    {
        // Solo se quitan las columnas; los enlaces rellenados se pierden con ellas.
        foreach (['source_id', 'source_type'] as $column) {
            if ($this->db->fieldExists($column, 'notifications')) {
                $this->db->query("ALTER TABLE `notifications` DROP COLUMN `{$column}`");
            }
        }
    }
}
