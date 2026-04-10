<?php

namespace App\Services;

use App\Models\SettingsModel;
use App\Models\LocationModel;
use App\Models\BonoTypeModel;
use App\Models\UserModel;

class ConfiguracionService
{
    protected SettingsModel  $settings;
    protected LocationModel  $locations;
    protected BonoTypeModel  $bonoTypes;
    protected UserModel      $users;

    public function __construct()
    {
        $this->settings  = new SettingsModel();
        $this->locations = new LocationModel();
        $this->bonoTypes = new BonoTypeModel();
        $this->users     = new UserModel();
    }

    // ════════════════════════════════════════════════════════════════
    //  SETTINGS GENERALES
    // ════════════════════════════════════════════════════════════════

    public function getAllSettings(): array
    {
        return $this->settings->getAll();
    }

    public function saveSettings(array $data, int $userId): void
    {
        $this->settings->setMultiple($data, $userId);
    }

    // ════════════════════════════════════════════════════════════════
    //  GESTIÓN DE STAFF
    // ════════════════════════════════════════════════════════════════

    /**
     * Devuelve usuarios con rol admin, staff o coach.
     * Excluye superadmin (no gestionable desde aquí).
     */
    public function getStaffUsers(): array
    {
        return $this->users
            ->whereIn('role', ['admin', 'staff', 'coach'])
            ->orderBy('role',  'ASC')
            ->orderBy('name',  'ASC')
            ->findAll();
    }

    /**
     * Crea un nuevo usuario de tipo staff (admin|staff|coach).
     * Genera contraseña automáticamente.
     *
     * @return array{success: bool, userId?: int, password?: string, name?: string, error?: string, errors?: array}
     */
    public function createStaffUser(array $data): array
    {
        $allowedRoles = ['admin', 'staff', 'coach'];
        if (!in_array($data['role'] ?? '', $allowedRoles)) {
            return ['success' => false, 'error' => 'Rol no válido.'];
        }

        $password = 'Jp' . bin2hex(random_bytes(3)) . '!';

        $id = $this->users->insert([
            'name'     => $data['name'],
            'email'    => $data['email'],
            'password' => $password,
            'role'     => $data['role'],
            'status'   => 'active',
        ]);

        if (!$id) {
            return [
                'success' => false,
                'error'   => 'No se pudo crear el usuario.',
                'errors'  => $this->users->errors(),
            ];
        }

        return [
            'success'  => true,
            'userId'   => $id,
            'password' => $password,
            'name'     => $data['name'],
        ];
    }

    /**
     * Cambia el rol de un usuario de staff.
     * Restricciones:
     *  - No se puede cambiar el propio rol
     *  - No se puede modificar un superadmin
     *  - El nuevo rol no puede ser 'superadmin' ni 'player'
     */
    public function updateStaffRole(int $userId, string $newRole, int $byUserId): bool
    {
        $allowed = ['admin', 'staff', 'coach'];
        if (!in_array($newRole, $allowed))  return false;
        if ($userId === $byUserId)          return false;

        $user = $this->users->find($userId);
        if (!$user || $user['role'] === 'superadmin') return false;

        return (bool) $this->users->skipValidation(true)->update($userId, ['role' => $newRole]);
    }

    /**
     * Desactiva (soft-delete) un usuario de staff.
     */
    public function deactivateStaffUser(int $userId, int $byUserId): bool
    {
        if ($userId === $byUserId) return false;

        $user = $this->users->find($userId);
        if (!$user || $user['role'] === 'superadmin') return false;

        return (bool) $this->users->skipValidation(true)->update($userId, ['status' => 'inactive']);
    }

    /**
     * Reactiva un usuario de staff previamente desactivado.
     */
    public function activateStaffUser(int $userId): bool
    {
        $user = $this->users->find($userId);
        if (!$user) return false;

        return (bool) $this->users->skipValidation(true)->update($userId, ['status' => 'active']);
    }

    // ════════════════════════════════════════════════════════════════
    //  CAMPOS Y SEDES
    // ════════════════════════════════════════════════════════════════

    public function getLocations(): array
    {
        return $this->locations->orderBy('name', 'ASC')->findAll();
    }

    public function getLocation(int $id): ?array
    {
        return $this->locations->find($id);
    }

    /**
     * @return array{success: bool, id?: int, errors?: array}
     */
    public function createLocation(array $data): array
    {
        $id = $this->locations->insert([
            'name'        => $data['name'],
            'description' => $data['description'] ?? null,
            'address'     => $data['address']     ?? null,
            'type'        => $data['type']         ?? 'pitch',
            'capacity'    => !empty($data['capacity']) ? (int)$data['capacity'] : null,
            'phone'       => $data['phone']        ?? null,
            'active'      => 1,
        ]);

        if (!$id) {
            return ['success' => false, 'errors' => $this->locations->errors()];
        }

        return ['success' => true, 'id' => $id];
    }

    public function updateLocation(int $id, array $data): bool
    {
        return (bool) $this->locations->skipValidation(false)->update($id, [
            'name'        => $data['name'],
            'description' => $data['description'] ?? null,
            'address'     => $data['address']     ?? null,
            'type'        => $data['type']         ?? 'pitch',
            'capacity'    => !empty($data['capacity']) ? (int)$data['capacity'] : null,
            'phone'       => $data['phone']        ?? null,
            'active'      => isset($data['active']) ? (int)$data['active'] : 1,
        ]);
    }

    public function deleteLocation(int $id): bool
    {
        return (bool) $this->locations->delete($id);
    }

    // ════════════════════════════════════════════════════════════════
    //  TIPOS DE BONO
    // ════════════════════════════════════════════════════════════════

    public function getBonoTypes(): array
    {
        return $this->bonoTypes->orderBy('name', 'ASC')->findAll();
    }

    /**
     * @return array{success: bool, id?: int, errors?: array}
     */
    public function createBonoType(array $data): array
    {
        $id = $this->bonoTypes->insert([
            'name'          => $data['name'],
            'sessions'      => (int)($data['sessions']      ?? 10),
            'price'         => (float)($data['price']       ?? 0),
            'validity_days' => (int)($data['validity_days'] ?? 90),
            'active'        => 1,
        ]);

        if (!$id) {
            return ['success' => false, 'errors' => $this->bonoTypes->errors()];
        }

        return ['success' => true, 'id' => $id];
    }

    public function updateBonoType(int $id, array $data): bool
    {
        return (bool) $this->bonoTypes->update($id, [
            'name'          => $data['name'],
            'sessions'      => (int)($data['sessions']      ?? 10),
            'price'         => (float)($data['price']       ?? 0),
            'validity_days' => (int)($data['validity_days'] ?? 90),
            'active'        => isset($data['active']) ? (int)$data['active'] : 1,
        ]);
    }

    public function deleteBonoType(int $id): bool
    {
        return (bool) $this->bonoTypes->delete($id);
    }

    // ════════════════════════════════════════════════════════════════
    //  NOTIFICACIONES — EMAIL
    // ════════════════════════════════════════════════════════════════

    /**
     * Envía un email (individual o grupal) usando la config SMTP almacenada.
     *
     * @param array{type: 'individual'|'group', recipient_id?: int, recipient_group?: string, subject: string, message: string} $data
     * @return array{success: bool, count?: int, error?: string}
     */
    public function sendEmail(array $data, int $senderId): array
    {
        $s = $this->settings->getAll();

        // Verificar que hay config SMTP
        if (empty($s['smtp_host']) || empty($s['smtp_from_email'])) {
            return ['success' => false, 'error' => 'La configuración SMTP no está completa.'];
        }

        // Determinar destinatarios
        $recipients = [];
        if ($data['type'] === 'individual') {
            $user = $this->users->find((int)($data['recipient_id'] ?? 0));
            if (!$user) {
                return ['success' => false, 'error' => 'Usuario no encontrado.'];
            }
            $recipients[] = ['email' => $user['email'], 'name' => $user['name']];
        } else {
            // Grupal
            $group = $data['recipient_group'] ?? 'all';
            $query = $this->users->where('status', 'active');
            if ($group !== 'all') {
                $query->where('role', $group);
            }
            $users = $query->findAll();
            foreach ($users as $u) {
                $recipients[] = ['email' => $u['email'], 'name' => $u['name']];
            }
        }

        if (empty($recipients)) {
            return ['success' => false, 'error' => 'No hay destinatarios para ese grupo.'];
        }

        // Configurar CI4 Email
        $emailService = \Config\Services::email();
        $emailService->initialize([
            'protocol'   => 'smtp',
            'SMTPHost'   => $s['smtp_host'],
            'SMTPPort'   => (int)$s['smtp_port'],
            'SMTPCrypto' => $s['smtp_encryption'],
            'SMTPUser'   => $s['smtp_user'],
            'SMTPPass'   => $s['smtp_pass'],
            'fromEmail'  => $s['smtp_from_email'],
            'fromName'   => $s['smtp_from_name'],
            'mailType'   => 'html',
            'charset'    => 'utf-8',
        ]);

        $sent   = 0;
        $errors = [];

        foreach ($recipients as $r) {
            try {
                $emailService->clear(true);
                $emailService->setTo($r['email'], $r['name']);
                $emailService->setSubject($data['subject']);
                $emailService->setMessage(nl2br(esc($data['message'])));

                if ($emailService->send(false)) {
                    $sent++;
                } else {
                    $errors[] = $r['email'];
                }
            } catch (\Throwable $e) {
                $errors[] = $r['email'] . ' (' . $e->getMessage() . ')';
            }
        }

        // Log del envío
        $this->logEmail($senderId, $data, $sent > 0 ? 'sent' : 'failed', implode(', ', $errors));

        if ($sent === 0) {
            return ['success' => false, 'error' => 'No se pudo enviar ningún email. Verifica la config SMTP.'];
        }

        return ['success' => true, 'count' => $sent];
    }

    private function logEmail(int $senderId, array $data, string $status, string $errorMsg = ''): void
    {
        try {
            \Config\Database::connect()->table('email_log')->insert([
                'sender_id'       => $senderId,
                'recipient_type'  => $data['type'],
                'recipient_id'    => $data['type'] === 'individual' ? ($data['recipient_id'] ?? null) : null,
                'recipient_group' => $data['type'] === 'group'      ? ($data['recipient_group'] ?? 'all') : null,
                'subject'         => $data['subject'],
                'message'         => $data['message'],
                'status'          => $status,
                'error_msg'       => $errorMsg ?: null,
                'created_at'      => date('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable $e) {
            log_message('error', 'email_log insert failed: ' . $e->getMessage());
        }
    }

    // ════════════════════════════════════════════════════════════════
    //  SEGURIDAD — LOG DE ACTIVIDAD
    // ════════════════════════════════════════════════════════════════

    /**
     * Devuelve las últimas entradas del log de actividad con nombre de usuario.
     */
    public function getRecentLogs(int $limit = 50): array
    {
        return \Config\Database::connect()
            ->table('logs l')
            ->select('l.*, u.name AS user_name, u.role AS user_role')
            ->join('users u', 'u.id = l.user_id', 'left')
            ->orderBy('l.created_at', 'DESC')
            ->limit($limit)
            ->get()
            ->getResultArray();
    }

    // ════════════════════════════════════════════════════════════════
    //  HELPERS — LISTAS PARA FORMULARIOS
    // ════════════════════════════════════════════════════════════════

    /**
     * Devuelve todos los usuarios activos para el selector de destinatario individual.
     */
    public function getAllActiveUsers(): array
    {
        return $this->users
            ->where('status', 'active')
            ->orderBy('name', 'ASC')
            ->findAll();
    }
}
