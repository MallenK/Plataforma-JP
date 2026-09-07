<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;
use App\Models\UserModel;

/**
 * Conversaciones (chat) y notificaciones de ejemplo para la demo, para que
 * esas secciones no salgan vacías al entrar como invitado.
 *
 * Se ejecuta DESPUÉS de BulkDemoDataSeeder y DemoGuestsSeeder (necesita
 * los usuarios ya creados). Idempotente: si el invitado-alumno ya tiene
 * alguna conversación, no hace nada.
 */
class DemoConversationsSeeder extends Seeder
{
    public function run()
    {
        helper('demo');
        $db        = $this->db ?: \Config\Database::connect();
        $userModel = new UserModel();

        $accounts = demo_guest_accounts();
        $guestAdmin  = $userModel->where('email', $accounts['admin']['email'])->first();
        $guestCoach  = $userModel->where('email', $accounts['coach']['email'])->first();
        $guestPlayer = $userModel->where('email', $accounts['player']['email'])->first();

        if (! $guestAdmin || ! $guestCoach || ! $guestPlayer) {
            echo "DemoConversationsSeeder: faltan cuentas de invitado; siembra DemoGuestsSeeder primero.\n";
            return;
        }
        $gAdmin  = (int) $guestAdmin['id'];
        $gCoach  = (int) $guestCoach['id'];
        $gPlayer = (int) $guestPlayer['id'];

        if ($this->hasConversation($db, $gPlayer)) {
            echo "DemoConversationsSeeder: ya hay conversaciones de demo, se omite.\n";
            return;
        }

        $adminId  = (int) ($userModel->whereIn('role', ['superadmin', 'admin'])
                        ->where('email !=', $accounts['admin']['email'])
                        ->orderBy('id', 'ASC')->first()['id'] ?? $gAdmin);
        $coaches  = array_column($userModel->where('role', 'coach')
                        ->where('email !=', $accounts['coach']['email'])
                        ->orderBy('id', 'ASC')->findAll(6), 'id');
        $players  = array_column($userModel->where('role', 'player')
                        ->where('email !=', $accounts['player']['email'])
                        ->orderBy('id', 'ASC')->findAll(12), 'id');

        $c1 = $coaches[0] ?? $adminId;
        $c2 = $coaches[1] ?? $c1;

        // ── Conversaciones ────────────────────────────────────────────────
        $this->conversation($db, $gPlayer, $c1, [
            [$c1,      '¡Hola! ¿Podrás venir al entreno de mañana a las 17:00?', '-3 days 18:10'],
            [$gPlayer, 'Sí, allí estaré. ¿Llevo botas de tacos o multitaco?',    '-3 days 18:25'],
            [$c1,      'Multitaco, mañana toca pista.',                          '-3 days 18:40'],
            [$c1,      'Recuerda traer la equipación azul para el vídeo.',       '-1 days 09:15'],
        ]);

        $this->conversation($db, $gPlayer, $c2, [
            [$c2,      'Buen trabajo hoy en la sesión de finalización 💪',        '-2 days 20:05'],
            [$gPlayer, '¡Gracias! ¿Me pasas el vídeo del ejercicio de control?', '-2 days 20:30'],
        ]);

        $this->conversation($db, $gCoach, $adminId, [
            [$adminId, '¿Puedes cubrir el grupo B el jueves? Marc está de baja.', '-2 days 11:00'],
            [$gCoach,  'Sin problema, lo cojo yo.',                               '-2 days 11:20'],
            [$adminId, 'Perfecto, te paso la lista de convocados.',               '-2 days 11:22'],
        ]);

        $this->conversation($db, $gAdmin, $c1, [
            [$c1,     'He subido los informes de seguimiento de septiembre.',   '-4 days 17:30'],
            [$gAdmin, 'Genial, los reviso esta tarde y te comento.',            '-4 days 19:00'],
        ]);

        if (! empty($players)) {
            $this->conversation($db, $c1, $players[0], [
                [$c1,          'Recuerda confirmar asistencia al partido del sábado.', '-1 days 10:00'],
                [$players[0],  'Confirmado, gracias.',                                  '-1 days 12:30'],
            ]);
        }

        // ── Notificaciones ────────────────────────────────────────────────
        $everyone = array_values(array_unique(array_merge(
            [$gAdmin, $gCoach, $gPlayer], $coaches, $players
        )));

        $this->notification($db, $adminId, 'group',
            'Bienvenido a la demo de la plataforma',
            "Estás viendo un entorno de demostración con datos ficticios. "
            . "Puedes navegar libremente: los datos se restablecen automáticamente cada noche.",
            $everyone, '-5 days 08:00', readForAll: false);

        $this->notification($db, $adminId, 'group',
            'Calendario de octubre publicado',
            "Ya está disponible el calendario de sesiones del próximo mes en el apartado Clases.",
            $everyone, '-3 days 09:30', readForAll: false);

        $this->notification($db, $adminId, 'individual',
            'Tu bono está a punto de caducar',
            "Te quedan 2 sesiones y el bono caduca en 6 días. Contacta con recepción para renovarlo.",
            [$gPlayer], '-1 days 08:15', readForAll: false);

        $this->notification($db, $adminId, 'group',
            'Recordatorio: revisión médica anual',
            "Los alumnos de categoría infantil y cadete deben entregar el certificado médico antes de fin de mes.",
            $everyone, '-6 hours', readForAll: true);

        echo "DemoConversationsSeeder: conversaciones y notificaciones creadas.\n";
    }

    private function hasConversation($db, int $userId): bool
    {
        return $db->table('conversations')
            ->groupStart()->where('user1_id', $userId)->orWhere('user2_id', $userId)->groupEnd()
            ->countAllResults() > 0;
    }

    /**
     * Crea una conversación entre dos usuarios y sus mensajes.
     * $messages: [ [senderId, body, 'strtotime relativo'], ... ]
     */
    private function conversation($db, int $a, int $b, array $messages): void
    {
        if ($a === $b) {
            return;
        }
        [$u1, $u2] = $a < $b ? [$a, $b] : [$b, $a];
        $first = date('Y-m-d H:i:s', strtotime($messages[0][2]));

        $db->table('conversations')->insert([
            'user1_id'        => $u1,
            'user2_id'        => $u2,
            'created_at'      => $first,
            'last_message_at' => $first,
        ]);
        $convId = (int) $db->insertID();

        $last = $first;
        $count = count($messages);
        foreach ($messages as $i => [$sender, $body, $when]) {
            $ts = date('Y-m-d H:i:s', strtotime($when));
            // El último mensaje recibido por cada invitado se deja sin leer.
            $isLast = $i === $count - 1;
            $db->table('messages')->insert([
                'conversation_id' => $convId,
                'sender_id'       => $sender,
                'body'            => $body,
                'read_at'         => $isLast ? null : $ts,
                'created_at'      => $ts,
            ]);
            $last = $ts;
        }
        $db->table('conversations')->where('id', $convId)->update(['last_message_at' => $last]);
    }

    /**
     * Crea una notificación con sus destinatarios.
     */
    private function notification($db, int $sender, string $type, string $title, string $body, array $recipients, string $when, bool $readForAll): void
    {
        $ts = date('Y-m-d H:i:s', strtotime($when));

        $db->table('notifications')->insert([
            'sender_id'  => $sender,
            'type'       => $type,
            'title'      => $title,
            'body'       => $body,
            'created_at' => $ts,
        ]);
        $notifId = (int) $db->insertID();

        $rows = [];
        foreach (array_unique($recipients) as $rid) {
            $rows[] = [
                'notification_id' => $notifId,
                'recipient_id'    => (int) $rid,
                'read_at'         => $readForAll ? $ts : null,
            ];
        }
        if ($rows) {
            $db->table('notification_recipients')->insertBatch($rows);
        }
    }
}
