<?php

namespace App\Controllers;

use App\Models\ConversationModel;
use App\Models\MessageModel;
use App\Models\UserModel;

class MensajesController extends BaseController
{
    private ConversationModel $convModel;
    private MessageModel      $msgModel;
    private UserModel         $userModel;
    private \CodeIgniter\Database\BaseConnection $db;

    // Roles que NO son jugadores
    private const NON_PLAYER_ROLES = ['superadmin', 'admin', 'coach', 'staff'];
    private const PLAYER_ROLES     = ['alumno', 'player'];

    public function initController(\CodeIgniter\HTTP\RequestInterface $request,
                                   \CodeIgniter\HTTP\ResponseInterface $response,
                                   \Psr\Log\LoggerInterface $logger): void
    {
        parent::initController($request, $response, $logger);
        $this->convModel = new ConversationModel();
        $this->msgModel  = new MessageModel();
        $this->userModel = new UserModel();
        $this->db        = \Config\Database::connect();
    }

    // ─────────────────────────────────────────────────────────
    // PÁGINA PRINCIPAL — listado de conversaciones
    // ─────────────────────────────────────────────────────────

    public function index(): string
    {
        $userId = $this->currentUserId();
        $role   = $this->currentRole();

        $conversations  = $this->convModel->getForUser($userId);
        $contactables   = $this->getContactableUsers($userId, $role);

        return view('mensajes/index', [
            'title'         => 'Mensajes',
            'conversations' => $conversations,
            'contactables'  => $contactables,
            'currentUserId' => $userId,
            'currentRole'   => $role,
        ]);
    }

    // ─────────────────────────────────────────────────────────
    // AJAX: obtener o crear conversación con un usuario
    // ─────────────────────────────────────────────────────────

    public function ajaxOpenConversation(): \CodeIgniter\HTTP\ResponseInterface
    {
        $userId    = (int) $this->currentUserId();
        $myRole    = (string) $this->currentRole();
        $otherId   = (int) $this->request->getPost('other_user_id');

        if ($userId <= 0) {
            return $this->jsonError('Sesión expirada. Vuelve a iniciar sesión.', 401);
        }
        if ($otherId <= 0 || $otherId === $userId) {
            return $this->jsonError('Usuario no válido.', 422);
        }

        $otherUser = $this->userModel->find($otherId);
        if (!$otherUser) {
            return $this->jsonError('Usuario no encontrado.', 404);
        }
        if (($otherUser['status'] ?? 'active') !== 'active') {
            return $this->jsonError('Este usuario no está activo.', 403);
        }

        // Regla: jugador no puede chatear con jugador
        if (!$this->canChat($myRole, $otherUser['role'])) {
            return $this->jsonError('Los jugadores no pueden chatear entre sí.', 403);
        }

        try {
            $conv = $this->convModel->findOrCreate($userId, $otherId);
        } catch (\Throwable $e) {
            log_message('error', 'MensajesController::ajaxOpenConversation findOrCreate failed: ' . $e->getMessage());
            return $this->jsonError('No se pudo abrir la conversación.', 500);
        }

        if (empty($conv['id'])) {
            return $this->jsonError('No se pudo abrir la conversación.', 500);
        }

        try {
            $this->msgModel->markReadInConversation($conv['id'], $userId);
            $messages = $this->msgModel->getForConversation($conv['id'], 50);
        } catch (\Throwable $e) {
            log_message('error', 'MensajesController::ajaxOpenConversation messages failed: ' . $e->getMessage());
            $messages = [];
        }

        return $this->response->setJSON([
            'conversation_id' => (int) $conv['id'],
            'other_user'      => [
                'id'     => (int) $otherUser['id'],
                'name'   => $otherUser['name'],
                'avatar' => $otherUser['avatar'] ?? null,
                'role'   => $otherUser['role'],
            ],
            'messages' => $messages,
            'csrf'     => csrf_hash(),
        ]);
    }

    // ─────────────────────────────────────────────────────────
    // AJAX: enviar un mensaje
    // ─────────────────────────────────────────────────────────

    public function ajaxSend(): \CodeIgniter\HTTP\ResponseInterface
    {
        $userId = $this->currentUserId();
        $myRole = $this->currentRole();
        $convId = (int) $this->request->getPost('conversation_id');
        $body   = trim($this->request->getPost('body') ?? '');

        if (!$convId) {
            return $this->jsonError('Conversación no válida.', 422);
        }

        // Verificar que el usuario pertenece a la conversación
        $conv = $this->convModel->find($convId);
        if (!$conv || ((int)$conv['user1_id'] !== $userId && (int)$conv['user2_id'] !== $userId)) {
            return $this->jsonError('Sin acceso a esta conversación.', 403);
        }

        // Verificar regla jugador-jugador
        $otherId   = (int)$conv['user1_id'] === $userId ? (int)$conv['user2_id'] : (int)$conv['user1_id'];
        $otherUser = $this->userModel->find($otherId);
        if (!$this->canChat($myRole, $otherUser['role'])) {
            return $this->jsonError('Los jugadores no pueden chatear entre sí.', 403);
        }

        // Archivo adjunto (opcional)
        $filePath = null;
        $fileName = null;
        $fileSize = null;
        $fileMime = null;
        $file = $this->request->getFile('attachment');

        if ($file && $file->isValid() && !$file->hasMoved()) {
            $result = $this->handleFileUpload($file, 'mensajes');
            if (isset($result['error'])) {
                return $this->jsonError($result['error'], 422);
            }
            $filePath = $result['path'];
            $fileName = $result['name'];
            $fileSize = $result['size'];
            $fileMime = $result['mime'];
        }

        if (!$body && !$filePath) {
            return $this->jsonError('El mensaje no puede estar vacío.', 422);
        }

        $now   = date('Y-m-d H:i:s');
        $msgId = $this->msgModel->insert([
            'conversation_id' => $convId,
            'sender_id'       => $userId,
            'body'            => $body ?: null,
            'file_path'       => $filePath,
            'file_name'       => $fileName,
            'file_size'       => $fileSize,
            'file_mime'       => $fileMime,
            'created_at'      => $now,
        ], true);

        $this->convModel->touchLastMessage($convId);

        $me = $this->currentUser();

        return $this->response->setJSON([
            'ok'      => true,
            'message' => [
                'id'             => $msgId,
                'conversation_id'=> $convId,
                'sender_id'      => $userId,
                'sender_name'    => $me['name'],
                'sender_avatar'  => $me['avatar'] ?? null,
                'sender_role'    => $myRole,
                'body'           => $body ?: null,
                'file_path'      => $filePath,
                'file_name'      => $fileName,
                'file_size'      => $fileSize,
                'file_mime'      => $fileMime,
                'created_at'     => $now,
            ],
            'csrf'    => csrf_hash(),
        ]);
    }

    // ─────────────────────────────────────────────────────────
    // AJAX: polling — mensajes nuevos desde un ID
    // ─────────────────────────────────────────────────────────

    public function ajaxPoll(int $convId): \CodeIgniter\HTTP\ResponseInterface
    {
        $userId  = $this->currentUserId();
        $sinceId = (int) ($this->request->getGet('since') ?? 0);

        $conv = $this->convModel->find($convId);
        if (!$conv || ((int)$conv['user1_id'] !== $userId && (int)$conv['user2_id'] !== $userId)) {
            return $this->jsonError('Sin acceso.', 403);
        }

        $messages = $this->db->table('messages m')
            ->select('m.*, u.name AS sender_name, u.avatar AS sender_avatar, u.role AS sender_role')
            ->join('users u', 'u.id = m.sender_id')
            ->where('m.conversation_id', $convId)
            ->where('m.id >', $sinceId)
            ->orderBy('m.created_at', 'ASC')
            ->get()->getResultArray();

        // Marcar como leídos los mensajes del otro
        if (!empty($messages)) {
            $this->msgModel->markReadInConversation($convId, $userId);
        }

        // Devolver IDs de mis mensajes ya leídos por el otro (para actualizar la UI)
        $readIds = $this->db->table('messages')
            ->select('id')
            ->where('conversation_id', $convId)
            ->where('sender_id', $userId)
            ->where('read_at IS NOT NULL', null, false)
            ->get()->getResultArray();
        $readIds = array_column($readIds, 'id');

        return $this->response->setJSON(['messages' => $messages, 'read_ids' => $readIds]);
    }

    // ─────────────────────────────────────────────────────────
    // AJAX: lista de conversaciones actualizada
    // ─────────────────────────────────────────────────────────

    public function ajaxConversations(): \CodeIgniter\HTTP\ResponseInterface
    {
        $userId = $this->currentUserId();
        return $this->response->setJSON([
            'conversations' => $this->convModel->getForUser($userId),
        ]);
    }

    // ─────────────────────────────────────────────────────────
    // Descarga de archivo de mensaje
    // ─────────────────────────────────────────────────────────

    public function download(int $msgId): mixed
    {
        $userId = $this->currentUserId();
        $msg    = $this->msgModel->find($msgId);

        if (!$msg || !$msg['file_path']) {
            return $this->response->setStatusCode(404);
        }

        // Verificar pertenencia a la conversación
        $conv = $this->convModel->find($msg['conversation_id']);
        if (!$conv || ((int)$conv['user1_id'] !== $userId && (int)$conv['user2_id'] !== $userId)) {
            return $this->response->setStatusCode(403);
        }

        $fullPath = FCPATH . $msg['file_path'];
        if (!file_exists($fullPath)) {
            return $this->response->setStatusCode(404);
        }

        return $this->response->download($fullPath, null)->setFileName($msg['file_name']);
    }

    // ─────────────────────────────────────────────────────────
    // Helpers privados
    // ─────────────────────────────────────────────────────────

    private function canChat(string $roleA, string $roleB): bool
    {
        // Jugador con jugador: prohibido
        return !(in_array($roleA, self::PLAYER_ROLES) && in_array($roleB, self::PLAYER_ROLES));
    }

    private function getContactableUsers(int $currentUserId, string $myRole): array
    {
        $builder = $this->userModel
            ->where('id !=', $currentUserId)
            ->where('status', 'active')
            ->select('id, name, role, avatar');

        // Si soy jugador, solo puedo contactar con no-jugadores
        if (in_array($myRole, self::PLAYER_ROLES)) {
            $builder->whereIn('role', self::NON_PLAYER_ROLES);
        }

        return $builder->orderBy('name', 'ASC')->findAll();
    }

    private function jsonError(string $msg, int $status = 400): \CodeIgniter\HTTP\ResponseInterface
    {
        return $this->response->setJSON(['error' => $msg])->setStatusCode($status);
    }

    private function handleFileUpload(\CodeIgniter\HTTP\Files\UploadedFile $file, string $subfolder): array
    {
        $maxSize = 5 * 1024 * 1024; // 5 MB
        $allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/gif',
                    'application/pdf', 'application/msword',
                    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                    'application/vnd.ms-excel',
                    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                    'text/plain', 'video/mp4'];

        if ($file->getSize() > $maxSize) {
            return ['error' => 'El archivo supera el límite de 5 MB.'];
        }

        $mime = $file->getMimeType();
        if (!in_array($mime, $allowed)) {
            return ['error' => 'Tipo de archivo no permitido.'];
        }

        $uploadDir = FCPATH . 'uploads/' . $subfolder . '/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $newName = uniqid('', true) . '_' . time() . '.' . $file->getClientExtension();
        $file->move($uploadDir, $newName);

        return [
            'path' => 'uploads/' . $subfolder . '/' . $newName,
            'name' => $file->getClientName(),
            'size' => $file->getSize(),
            'mime' => $mime,
        ];
    }
}
