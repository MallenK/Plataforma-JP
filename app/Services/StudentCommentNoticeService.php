<?php

namespace App\Services;

use App\Models\NotificationModel;
use App\Models\UserModel;

/**
 * Aviso cuando un alumno deja una observación en su propio perfil: lo reciben
 * los admin/superadmin activos y, como máximo, dos entrenadores de sus
 * próximas clases programadas.
 */
class StudentCommentNoticeService
{
    public const MAX_COACHES = 2;

    /** Caracteres del comentario que viajan en el aviso. */
    private const EXCERPT = 300;

    /** Nunca lanza: un fallo al avisar no debe impedir que el comentario quede guardado. */
    public function notify(int $studentId, string $content, bool $hasFile = false): void
    {
        try {
            $users   = new UserModel();
            $student = $users->select('name')->find($studentId);

            $admins = $users->select('id')
                ->whereIn('role', ['admin', 'superadmin'])
                ->where('status', 'active')
                ->findAll();

            $recipients = array_merge(
                array_map(static fn($a) => (int) $a['id'], $admins),
                self::pickCoaches($this->upcomingCoachRows($studentId))
            );
            $recipients = array_values(array_unique(array_filter(
                $recipients,
                static fn($id) => $id > 0 && $id !== $studentId
            )));
            if ($recipients === []) {
                return;
            }

            $msg = self::buildMessage($student['name'] ?? 'Un alumno', $content, $hasFile);

            (new NotificationModel())->createWithRecipients([
                'sender_id'   => $studentId,
                'type'        => 'individual',
                'title'       => $msg['title'],
                'body'        => $msg['body'],
                'source_type' => NotificationModel::SOURCE_PLAYER,
                'source_id'   => $studentId,
            ], $recipients);
        } catch (\Throwable $e) {
            log_message('error', 'StudentCommentNoticeService::notify falló: ' . $e->getMessage());
        }
    }

    /**
     * Entrenadores de las próximas clases del alumno, de la más cercana a la
     * más lejana. Si el alumno tiene entrenador asignado dentro de la clase
     * (`coach_id`) manda ese; si no, los entrenadores de la sesión.
     */
    private function upcomingCoachRows(int $studentId): array
    {
        $db  = \Config\Database::connect();
        $now = $db->escape(date('Y-m-d H:i:s'));

        return $db->table('class_session_players csp')
            ->select('u.id AS coach_id')
            ->join('class_sessions cs', 'cs.id = csp.session_id')
            ->join('class_session_coaches csc', 'csc.session_id = cs.id', 'left')
            // El staff queda fuera: no tiene acceso a la ficha del alumno.
            ->join('users u', 'u.id = COALESCE(csp.coach_id, csc.user_id)')
            ->where('csp.user_id', $studentId)
            ->where('cs.status', 'scheduled')
            ->where("TIMESTAMP(cs.session_date, cs.start_time) >= {$now}", null, false)
            ->where('u.role', 'coach')
            ->where('u.status', 'active')
            ->orderBy('cs.session_date')
            ->orderBy('cs.start_time')
            ->orderBy('u.name')
            ->limit(50)
            ->get()->getResultArray();
    }

    /**
     * Primeros entrenadores distintos, respetando el orden recibido. Puro.
     *
     * @param  list<array{coach_id: int|string|null}> $rows
     * @return list<int>
     */
    public static function pickCoaches(array $rows, int $max = self::MAX_COACHES): array
    {
        $picked = [];
        foreach ($rows as $row) {
            $id = (int) ($row['coach_id'] ?? 0);
            if ($id > 0 && !in_array($id, $picked, true)) {
                $picked[] = $id;
                if (count($picked) >= $max) {
                    break;
                }
            }
        }

        return $picked;
    }

    /**
     * Título y cuerpo del aviso. Puro.
     *
     * @return array{title: string, body: string}
     */
    public static function buildMessage(string $studentName, string $content, bool $hasFile): array
    {
        $content = trim(preg_replace('/\s+/u', ' ', $content) ?? '');
        if (mb_strlen($content) > self::EXCERPT) {
            $content = rtrim(mb_substr($content, 0, self::EXCERPT)) . '…';
        }

        $body = sprintf('%s ha escrito en su perfil: "%s"', $studentName, $content);
        if ($hasFile) {
            $body .= ' Incluye un archivo adjunto.';
        }

        return [
            'title' => '📝 Nuevo comentario de ' . $studentName,
            'body'  => $body,
        ];
    }
}
