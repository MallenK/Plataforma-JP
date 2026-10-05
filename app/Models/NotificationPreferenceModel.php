<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Qué notificaciones quiere recibir cada usuario. Por categoría y canal:
 *  - in_app: centro de notificaciones (campanita)
 *  - push:   aviso en el dispositivo; solo tiene sentido si in_app está activo
 *            (el aviso abre la notificación del centro), por eso se fuerza a off.
 * Sin fila = todo activado. Si la tabla aún no existe (entorno sin migrar) se
 * comporta como "todo activado": una preferencia nunca debe perder un aviso.
 */
class NotificationPreferenceModel extends Model
{
    protected $table         = 'notification_preferences';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $allowedFields = ['user_id', 'category', 'in_app', 'push', 'updated_at'];

    public const CAT_MENSAJES = 'mensajes';
    public const CAT_CLASES   = 'clases';
    public const CAT_BONOS    = 'bonos';
    public const CAT_TICKETS  = 'tickets';
    public const CAT_AVISOS   = 'avisos';

    /** @return array<string, array{label: string, desc: string, icon: string}> */
    public static function categories(): array
    {
        return [
            self::CAT_MENSAJES => ['label' => 'Mensajes', 'icon' => 'bi-chat-dots',
                'desc' => 'Cuando alguien te escribe en el chat.'],
            self::CAT_CLASES   => ['label' => 'Clases', 'icon' => 'bi-calendar3',
                'desc' => 'Altas, cambios de hora o sede, cancelaciones, cambio de responsable y avisos de asistencia.'],
            self::CAT_BONOS    => ['label' => 'Bonos', 'icon' => 'bi-ticket-perforated',
                'desc' => 'Saldo, consumo, caducidad y renovación de bonos.'],
            self::CAT_TICKETS  => ['label' => 'Soporte (tickets)', 'icon' => 'bi-life-preserver',
                'desc' => 'Respuestas, cambios de estado y asignaciones de tus tickets.'],
            self::CAT_AVISOS   => ['label' => 'Avisos de la academia', 'icon' => 'bi-megaphone',
                'desc' => 'Comunicados y notificaciones enviados por el equipo de JP Preparation.'],
        ];
    }

    /**
     * Categoría de una notificación. Manda `category` si el que la crea la indica
     * (avisos de bonos, que no tienen origen enlazable); si no, se deduce del origen
     * (misma clasificación que el enlace de la campanita) y lo demás son avisos.
     */
    public static function categoryFor(array $notification): string
    {
        if (isset($notification['category']) && isset(self::categories()[$notification['category']])) {
            return $notification['category'];
        }

        return match ($notification['source_type'] ?? null) {
            NotificationModel::SOURCE_CONVERSATION => self::CAT_MENSAJES,
            NotificationModel::SOURCE_CLASS        => self::CAT_CLASES,
            NotificationModel::SOURCE_BONO         => self::CAT_BONOS,
            NotificationModel::SOURCE_TICKET       => self::CAT_TICKETS,
            default                                => self::CAT_AVISOS,
        };
    }

    /**
     * Preferencias efectivas de un usuario para TODAS las categorías.
     *
     * @return array<string, array{in_app: bool, push: bool}>
     */
    public function forUser(int $userId): array
    {
        $out = [];
        foreach (array_keys(self::categories()) as $cat) {
            $out[$cat] = ['in_app' => true, 'push' => true];
        }

        try {
            foreach ($this->where('user_id', $userId)->findAll() as $row) {
                if (isset($out[$row['category']])) {
                    $inApp = (bool) $row['in_app'];
                    $out[$row['category']] = ['in_app' => $inApp, 'push' => $inApp && (bool) $row['push']];
                }
            }
        } catch (\Throwable $e) {
            log_message('warning', '[NotificationPreferenceModel] forUser: ' . $e->getMessage());
        }

        return $out;
    }

    /**
     * Guarda las preferencias a partir de lo enviado por el formulario
     * (`pref[categoria][in_app|push]` = '1' si está marcado; lo ausente = apagado).
     * Solo se aceptan las categorías conocidas.
     */
    public function saveForUser(int $userId, array $input): void
    {
        $now = date('Y-m-d H:i:s');
        foreach (array_keys(self::categories()) as $cat) {
            $inApp = !empty($input[$cat]['in_app']);
            $push  = $inApp && !empty($input[$cat]['push']);

            $existing = $this->where(['user_id' => $userId, 'category' => $cat])->first();
            $data = ['in_app' => (int) $inApp, 'push' => (int) $push, 'updated_at' => $now];

            if ($existing) {
                $this->update($existing['id'], $data);
            } else {
                $this->insert($data + ['user_id' => $userId, 'category' => $cat]);
            }
        }
    }

    /**
     * De una lista de destinatarios, quiénes NO quieren esa categoría.
     *
     * @param  list<int> $userIds
     * @return array{in_app_off: list<int>, push_off: list<int>}
     */
    public function mutedFor(array $userIds, string $category): array
    {
        $none = ['in_app_off' => [], 'push_off' => []];
        if ($userIds === []) {
            return $none;
        }

        try {
            $rows = $this->select('user_id, in_app, push')
                ->where('category', $category)
                ->whereIn('user_id', array_map('intval', $userIds))
                ->findAll();
        } catch (\Throwable $e) {
            log_message('warning', '[NotificationPreferenceModel] mutedFor: ' . $e->getMessage());
            return $none;
        }

        $out = $none;
        foreach ($rows as $r) {
            if (!(int) $r['in_app']) {
                $out['in_app_off'][] = (int) $r['user_id'];
            }
            if (!(int) $r['in_app'] || !(int) $r['push']) {
                $out['push_off'][] = (int) $r['user_id'];
            }
        }
        return $out;
    }
}
