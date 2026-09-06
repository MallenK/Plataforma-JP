<?php

namespace App\Controllers;

use App\Models\NotificationModel;
use App\Models\UserModel;

class NotificacionesController extends BaseController
{
    private NotificationModel $notifModel;
    private UserModel         $userModel;
    private \CodeIgniter\Database\BaseConnection $db;

    public function initController(\CodeIgniter\HTTP\RequestInterface $request,
                                   \CodeIgniter\HTTP\ResponseInterface $response,
                                   \Psr\Log\LoggerInterface $logger): void
    {
        parent::initController($request, $response, $logger);
        $this->notifModel = new NotificationModel();
        $this->userModel  = new UserModel();
        $this->db         = \Config\Database::connect();
    }

    // ─────────────────────────────────────────────────────────
    // PÁGINA PRINCIPAL — centro de notificaciones
    // ─────────────────────────────────────────────────────────

    public function index(): string
    {
        $userId = $this->currentUserId();
        $role   = $this->currentRole();

        // Por diseño: un alumno SÍ puede enviar notificaciones individuales,
        // pero solo a NO-alumnos (misma regla que Mensajes; se aplica en
        // resolveRecipients() + rate-limit por remitente). El coach es el
        // único rol sin envío de notificaciones (usa Mensajes).
        $canSendNotif = !in_array($role, ['coach']);
        $canSendGroup = in_array($role, ['superadmin', 'admin']);
        $canSeeSent   = in_array($role, ['superadmin', 'admin']);

        // Destinatarios disponibles para notificación individual
        $recipients = $this->userModel
            ->where('id !=', $userId)
            ->where('status', 'active')
            ->select('id, name, role, avatar')
            ->orderBy('name', 'ASC')
            ->findAll();

        // Grupos de destinatarios para notificación grupal
        $groups = [];
        if ($canSendGroup) {
            $groups = $this->buildGroups();
        }

        return view('notificaciones/index', [
            'title'             => 'Notificaciones',
            'notifications'     => $this->notifModel->getForUser($userId, 30),
            'unread'            => $this->notifModel->countUnread($userId),
            'sentNotifications' => $canSeeSent ? $this->notifModel->getSentByUser($userId, 30) : [],
            'canSeeSent'        => $canSeeSent,
            'canSendNotif'      => $canSendNotif,
            'recipients'        => $recipients,
            'groups'            => $groups,
            'canSendGroup'      => $canSendGroup,
        ]);
    }

    // ─────────────────────────────────────────────────────────
    // AJAX: últimas N notificaciones (campana del navbar)
    // ─────────────────────────────────────────────────────────

    public function ajaxLatest(): \CodeIgniter\HTTP\ResponseInterface
    {
        $userId = $this->currentUserId();
        $notifs = $this->notifModel->getForUser($userId, 10);
        $unread = $this->notifModel->countUnread($userId);

        return $this->response->setJSON([
            'unread'        => $unread,
            'notifications' => $notifs,
        ]);
    }

    // ─────────────────────────────────────────────────────────
    // AJAX: marcar una notificación como leída
    // ─────────────────────────────────────────────────────────

    public function ajaxMarkRead(int $id): \CodeIgniter\HTTP\ResponseInterface
    {
        $this->notifModel->markRead($this->currentUserId(), $id);
        return $this->response->setJSON(['ok' => true]);
    }

    // ─────────────────────────────────────────────────────────
    // AJAX: marcar todas como leídas
    // ─────────────────────────────────────────────────────────

    public function ajaxMarkAllRead(): \CodeIgniter\HTTP\ResponseInterface
    {
        $this->notifModel->markAllRead($this->currentUserId());
        return $this->response->setJSON(['ok' => true]);
    }

    // ─────────────────────────────────────────────────────────
    // ENVIAR notificación individual o grupal
    // ─────────────────────────────────────────────────────────

    /** Máx. notificaciones que un usuario puede crear en la ventana (anti-spam). */
    private const RATE_WINDOW_MIN   = 10;
    private const RATE_MAX_PLAYER   = 8;
    private const RATE_MAX_STANDARD = 40;

    public function send(): \CodeIgniter\HTTP\ResponseInterface
    {
        $userId = (int) $this->currentUserId();
        $role   = (string) $this->currentRole();

        // Solo se aceptan estos dos tipos; cualquier otro valor se rechaza
        // (antes un 'type' inventado por un player se colaba en la rama grupal).
        $type = $this->request->getPost('type') === 'group' ? 'group' : 'individual';

        // Coach no puede enviar notificaciones (solo mensajes)
        if ($role === 'coach') {
            return $this->response->setJSON(['error' => 'Los entrenadores no pueden enviar notificaciones. Usa el apartado de Mensajes.'])->setStatusCode(403);
        }

        // Validaciones básicas
        $title = trim($this->request->getPost('title') ?? '');
        $body  = trim($this->request->getPost('body') ?? '');

        if (!$title || !$body) {
            return $this->response->setJSON(['error' => 'Título y mensaje son obligatorios.'])->setStatusCode(422);
        }

        // Solo admin/superadmin pueden enviar grupal
        if ($type === 'group' && !in_array($role, ['superadmin', 'admin'])) {
            return $this->response->setJSON(['error' => 'Sin permisos para notificaciones grupales.'])->setStatusCode(403);
        }

        // Rate-limit anti-spam (por remitente, ventana móvil)
        $isPlayer = in_array($role, ['player', 'alumno'], true);
        $rateMax  = $isPlayer ? self::RATE_MAX_PLAYER : self::RATE_MAX_STANDARD;
        if ($this->notifModel->countRecentBySender($userId, self::RATE_WINDOW_MIN) >= $rateMax) {
            return $this->response->setJSON(['error' => 'Has enviado demasiadas notificaciones en poco tiempo. Espera unos minutos.'])->setStatusCode(429);
        }

        // Resolver destinatarios
        $recipientIds = $this->resolveRecipients($type, $userId, $role);

        if (empty($recipientIds)) {
            return $this->response->setJSON(['error' => 'No se encontraron destinatarios válidos.'])->setStatusCode(422);
        }

        // Archivo adjunto (opcional)
        $filePath = null;
        $fileName = null;
        $fileSize = null;
        $file     = $this->request->getFile('attachment');

        if ($file && $file->isValid() && !$file->hasMoved()) {
            $result = $this->handleFileUpload($file, 'notificaciones');
            if (isset($result['error'])) {
                return $this->response->setJSON($result)->setStatusCode(422);
            }
            $filePath = $result['path'];
            $fileName = $result['name'];
            $fileSize = $result['size'];
        }

        $notifId = $this->notifModel->createWithRecipients([
            'sender_id' => $userId,
            'type'      => $type,
            'title'     => $title,
            'body'      => $body,
            'file_path' => $filePath,
            'file_name' => $fileName,
            'file_size' => $fileSize,
        ], $recipientIds);

        return $this->response->setJSON([
            'ok'              => true,
            'notification_id' => $notifId,
            'recipients'      => count($recipientIds),
            'csrf'            => csrf_hash(),
        ]);
    }

    // ─────────────────────────────────────────────────────────
    // DESCARGA de archivo adjunto de notificación
    // ─────────────────────────────────────────────────────────

    public function download(int $id): mixed
    {
        $userId = $this->currentUserId();

        // Verificar que el usuario es destinatario o remitente
        $notif = $this->notifModel->find($id);
        if (!$notif) {
            return $this->response->setStatusCode(404);
        }

        $isRecipient = (bool) $this->db->table('notification_recipients')
            ->where('notification_id', $id)
            ->where('recipient_id', $userId)
            ->countAllResults();

        if (!$isRecipient && $notif['sender_id'] !== $userId) {
            return $this->response->setStatusCode(403);
        }

        if (!$notif['file_path']) {
            return $this->response->setStatusCode(404);
        }

        helper('upload');
        $fullPath = upload_resolve_stored($notif['file_path']);
        if ($fullPath === null) {
            return $this->response->setStatusCode(404);
        }

        return $this->response->download($fullPath, null)->setFileName($notif['file_name']);
    }

    // ─────────────────────────────────────────────────────────
    // Helpers privados
    // ─────────────────────────────────────────────────────────

    private function resolveRecipients(string $type, int $senderId, string $senderRole = ''): array
    {
        if ($type === 'individual') {
            $recipientId = (int) $this->request->getPost('recipient_id');
            if (!$recipientId || $recipientId === $senderId) {
                return [];
            }

            $recipient = $this->userModel->find($recipientId);
            if (!$recipient || ($recipient['status'] ?? 'active') !== 'active') {
                return [];
            }

            // Misma regla que Mensajes: un jugador solo puede dirigirse a
            // no-jugadores (nada de spam jugador → jugador).
            $senderIsPlayer    = in_array($senderRole, ['player', 'alumno'], true);
            $recipientIsPlayer = in_array($recipient['role'] ?? '', ['player', 'alumno'], true);
            if ($senderIsPlayer && $recipientIsPlayer) {
                return [];
            }

            return [$recipientId];
        }

        // Grupal: filtrar por grupo seleccionado
        $group = $this->request->getPost('group'); // 'all' | 'players' | 'coaches' | 'staff'

        $roleMap = [
            'all'     => null,
            'players' => ['alumno', 'player'],
            'coaches' => ['coach'],
            'staff'   => ['staff', 'admin', 'superadmin'],
        ];

        $builder = $this->userModel
            ->where('id !=', $senderId)
            ->where('status', 'active')
            ->select('id');

        if (isset($roleMap[$group]) && $roleMap[$group] !== null) {
            $builder->whereIn('role', $roleMap[$group]);
        }

        return array_column($builder->findAll(), 'id');
    }

    private function buildGroups(): array
    {
        return [
            'all'     => 'Todos los usuarios',
            'players' => 'Todos los jugadores',
            'coaches' => 'Todos los entrenadores',
            'staff'   => 'Staff / Administración',
        ];
    }

    /** Extensiones permitidas en adjuntos de notificación (lista blanca real). */
    private const ALLOWED_EXTENSIONS = [
        'jpg', 'jpeg', 'png', 'webp', 'gif',
        'pdf', 'doc', 'docx', 'xls', 'xlsx',
        'txt', 'mp4',
    ];

    private function handleFileUpload(\CodeIgniter\HTTP\Files\UploadedFile $file, string $subfolder): array
    {
        helper('upload');

        $maxSize  = 5 * 1024 * 1024; // 5 MB
        $allowed  = ['image/jpeg', 'image/png', 'image/webp', 'image/gif',
                     'application/pdf', 'application/msword',
                     'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                     'application/vnd.ms-excel',
                     'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                     'text/plain', 'video/mp4'];

        if ($file->getSize() > $maxSize) {
            return ['error' => 'El archivo supera el límite de 5 MB.'];
        }

        if (!in_array($file->getMimeType(), $allowed)) {
            return ['error' => 'Tipo de archivo no permitido.'];
        }

        // La extensión declarada por el cliente NO es de confianza: se valida
        // contra lista blanca para que no se pueda guardar un .php camuflado
        // con un MIME permitido (p. ej. polyglot GIF89a + <?php …).
        $ext = upload_allowed_extension($file->getClientExtension(), self::ALLOWED_EXTENSIONS);
        if ($ext === null) {
            return ['error' => 'Extensión de archivo no permitida.'];
        }

        // Fuera del webroot: solo se sirve por NotificacionesController::download().
        $uploadDir = upload_private_dir($subfolder);
        upload_harden_dir($uploadDir);

        $newName = bin2hex(random_bytes(16)) . '.' . $ext;
        $file->move($uploadDir, $newName);

        return [
            'path' => upload_stored_path($subfolder, $newName),
            'name' => $file->getClientName(),
            'size' => $file->getSize(),
        ];
    }
}
