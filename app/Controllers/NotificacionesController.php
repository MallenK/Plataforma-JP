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

    public function send(): \CodeIgniter\HTTP\ResponseInterface
    {
        $userId = $this->currentUserId();
        $role   = $this->currentRole();
        $type   = $this->request->getPost('type'); // 'individual' | 'group'

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

        // Resolver destinatarios
        $recipientIds = $this->resolveRecipients($type, $userId);

        if (empty($recipientIds)) {
            return $this->response->setJSON(['error' => 'No se encontraron destinatarios.'])->setStatusCode(422);
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

        $fullPath = FCPATH . $notif['file_path'];
        if (!file_exists($fullPath)) {
            return $this->response->setStatusCode(404);
        }

        return $this->response->download($fullPath, null)->setFileName($notif['file_name']);
    }

    // ─────────────────────────────────────────────────────────
    // Helpers privados
    // ─────────────────────────────────────────────────────────

    private function resolveRecipients(string $type, int $senderId): array
    {
        if ($type === 'individual') {
            $recipientId = (int) $this->request->getPost('recipient_id');
            if (!$recipientId) return [];
            // El remitente no se incluye a sí mismo
            return $recipientId !== $senderId ? [$recipientId] : [];
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

        $uploadDir = FCPATH . 'uploads/' . $subfolder . '/';
        upload_harden_dir($uploadDir);

        $newName = bin2hex(random_bytes(16)) . '.' . $ext;
        $file->move($uploadDir, $newName);

        return [
            'path' => 'uploads/' . $subfolder . '/' . $newName,
            'name' => $file->getClientName(),
            'size' => $file->getSize(),
        ];
    }
}
