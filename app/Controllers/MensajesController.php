<?php

namespace App\Controllers;

use App\Models\ConversationModel;
use App\Models\MessageModel;
use App\Models\NotificationModel;
use App\Models\TicketModel;
use App\Models\UserModel;

class MensajesController extends BaseController
{
    private ConversationModel $convModel;
    private MessageModel      $msgModel;
    private NotificationModel $notifModel;
    private TicketModel       $ticketModel;
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
        $this->convModel   = new ConversationModel();
        $this->msgModel    = new MessageModel();
        $this->notifModel  = new NotificationModel();
        $this->ticketModel = new TicketModel();
        $this->userModel   = new UserModel();
        $this->db          = \Config\Database::connect();
    }

    // ─────────────────────────────────────────────────────────
    // PÁGINA PRINCIPAL — listado de conversaciones
    // ─────────────────────────────────────────────────────────

    public function index()
    {
        $userId = $this->currentUserId();
        $role   = $this->currentRole();

        try {
            $conversations = $this->convModel->getForUser($userId);
            $contactables  = $this->getContactableUsers($userId, $role);
        } catch (\Throwable $e) {
            $ref = $this->logAndRef($e, 'index');
            return redirect()->to('/dashboard')
                ->with('error', "No se pudo cargar Mensajes. Inténtalo de nuevo. (Ref: {$ref})");
        }

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
        try {
            $userId  = (int) $this->currentUserId();
            $myRole  = (string) $this->currentRole();
            $otherId = (int) $this->request->getPost('other_user_id');

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

            $conv = $this->convModel->findOrCreate($userId, $otherId);
            if (empty($conv['id'])) {
                $ref = $this->logAndRef(new \RuntimeException('findOrCreate devolvió vacío'), 'ajaxOpenConversation');
                return $this->jsonError('No se pudo abrir la conversación.', 500, $ref);
            }

            try {
                $this->msgModel->markReadInConversation($conv['id'], $userId);
                $messages = $this->msgModel->getForConversation($conv['id'], 50);
            } catch (\Throwable $e) {
                // No bloqueamos la apertura de la conversación por un fallo al
                // cargar el historial — se abre vacía y se registra el error.
                $this->logAndRef($e, 'ajaxOpenConversation:messages');
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
        } catch (\Throwable $e) {
            $ref = $this->logAndRef($e, 'ajaxOpenConversation');
            return $this->jsonError('Ha ocurrido un error inesperado al abrir la conversación.', 500, $ref);
        }
    }

    // ─────────────────────────────────────────────────────────
    // AJAX: enviar un mensaje
    // ─────────────────────────────────────────────────────────

    public function ajaxSend(): \CodeIgniter\HTTP\ResponseInterface
    {
        try {
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

        try {
            $preview = $body ? mb_strimwidth($body, 0, 80, '…') : '📎 ' . ($fileName ?? 'Archivo adjunto');
            $this->notifModel->createWithRecipients([
                'sender_id'   => $userId,
                'type'        => 'individual',
                'title'       => 'Nuevo mensaje de ' . $me['name'],
                'body'        => $preview,
                'created_at'  => $now,
                'source_type' => 'conversation',
                'source_id'   => $convId,
            ], [$otherId]);
        } catch (\Throwable $e) {
            log_message('error', 'MensajesController::ajaxSend notification failed: ' . $e->getMessage());
        }

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
        } catch (\Throwable $e) {
            $ref = $this->logAndRef($e, 'ajaxSend');
            return $this->jsonError('Ha ocurrido un error inesperado al enviar el mensaje.', 500, $ref);
        }
    }

    // ─────────────────────────────────────────────────────────
    // AJAX: polling — mensajes nuevos desde un ID
    // ─────────────────────────────────────────────────────────

    public function ajaxPoll(int $convId): \CodeIgniter\HTTP\ResponseInterface
    {
        try {
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
        } catch (\Throwable $e) {
            $ref = $this->logAndRef($e, 'ajaxPoll');
            return $this->jsonError('Ha ocurrido un error inesperado al comprobar mensajes nuevos.', 500, $ref);
        }
    }

    // ─────────────────────────────────────────────────────────
    // AJAX: lista de conversaciones actualizada
    // ─────────────────────────────────────────────────────────

    public function ajaxConversations(): \CodeIgniter\HTTP\ResponseInterface
    {
        try {
            $userId = $this->currentUserId();
            return $this->response->setJSON([
                'conversations' => $this->convModel->getForUser($userId),
            ]);
        } catch (\Throwable $e) {
            $ref = $this->logAndRef($e, 'ajaxConversations');
            return $this->jsonError('Ha ocurrido un error inesperado al cargar las conversaciones.', 500, $ref);
        }
    }

    // ─────────────────────────────────────────────────────────
    // Descarga de archivo de mensaje
    // ─────────────────────────────────────────────────────────

    public function download(int $msgId): mixed
    {
        try {
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
        } catch (\Throwable $e) {
            $this->logAndRef($e, 'download');
            return $this->response->setStatusCode(500);
        }
    }

    // ─────────────────────────────────────────────────────────
    // AJAX: reportar un error de la interfaz de Mensajes.
    // Crea un ticket automático dirigido al superadmin con el detalle
    // técnico + el comentario opcional del usuario. Disponible para
    // cualquier rol autenticado (incluido player, que normalmente no
    // tiene acceso a /tickets).
    // ─────────────────────────────────────────────────────────

    public function reportError(): \CodeIgniter\HTTP\ResponseInterface
    {
        try {
            $userId = $this->currentUserId();
            $user   = $this->currentUser();

            $context  = trim((string) $this->request->getPost('context')) ?: 'desconocido';
            $errorMsg = trim((string) $this->request->getPost('error_detail'));
            $errorRef = trim((string) $this->request->getPost('error_ref'));
            $comment  = trim((string) $this->request->getPost('user_comment'));
            $pageUrl  = trim((string) $this->request->getPost('page_url'));
            $userAgent = (string) $this->request->getUserAgent();

            $descLines = [
                'Reporte automático generado desde Mensajes.',
                '',
                'Acción que falló: ' . $context,
                'Usuario: ' . ($user['name'] ?? '—') . ' (' . ($user['email'] ?? '—') . ', rol: ' . ($user['role'] ?? '—') . ')',
                'URL: ' . ($pageUrl ?: '—'),
                'Fecha: ' . date('Y-m-d H:i:s'),
            ];
            if ($errorRef !== '') {
                $descLines[] = 'Referencia de error del servidor: ' . $errorRef;
            }
            if ($errorMsg !== '') {
                $descLines[] = 'Detalle técnico: ' . mb_substr($errorMsg, 0, 500);
            }
            $descLines[] = 'Navegador: ' . mb_substr($userAgent, 0, 200);
            if ($comment !== '') {
                $descLines[] = '';
                $descLines[] = 'Comentario del usuario:';
                $descLines[] = mb_substr($comment, 0, 1000);
            }

            $ticketId = $this->ticketModel->createTicket([
                'user_id'     => $userId,
                'title'       => 'Error automático — Mensajes: ' . mb_substr($context, 0, 80),
                'description' => implode("\n", $descLines),
                'category'    => 'bug',
                'priority'    => 'alta',
            ]);

            if (!$ticketId) {
                return $this->jsonError('No se pudo crear el reporte. Inténtalo de nuevo.', 500);
            }

            // Notificar a los superadmins (mismo patrón que TicketsController::notifyAdmins)
            try {
                $superadmins = $this->userModel
                    ->where('role', 'superadmin')
                    ->where('id !=', $userId)
                    ->where('status', 'active')
                    ->select('id')
                    ->findAll();
                $ids = array_column($superadmins, 'id');
                if (!empty($ids)) {
                    $ticket = $this->ticketModel->find($ticketId);
                    $this->notifModel->createWithRecipients([
                        'sender_id'   => $userId,
                        'type'        => 'individual',
                        'title'       => 'Nuevo ticket: ' . $ticket['ticket_number'],
                        'body'        => $ticket['title'],
                        'created_at'  => date('Y-m-d H:i:s'),
                        'source_type' => 'ticket',
                        'source_id'   => $ticketId,
                    ], $ids);
                }
            } catch (\Throwable $e) {
                // La notificación es secundaria — el ticket ya existe aunque falle.
                $this->logAndRef($e, 'reportError:notify');
            }

            $ticket = $this->ticketModel->find($ticketId);

            return $this->response->setJSON([
                'ok'            => true,
                'ticket_number' => $ticket['ticket_number'] ?? null,
                'csrf'          => csrf_hash(),
            ]);
        } catch (\Throwable $e) {
            $ref = $this->logAndRef($e, 'reportError');
            return $this->jsonError('No se pudo enviar el reporte. Inténtalo de nuevo. (Ref: ' . $ref . ')', 500);
        }
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

    private function jsonError(string $msg, int $status = 400, ?string $ref = null): \CodeIgniter\HTTP\ResponseInterface
    {
        $payload = ['error' => $msg];
        if ($ref) {
            $payload['error_ref'] = $ref;
        }
        return $this->response->setJSON($payload)->setStatusCode($status);
    }

    /**
     * Registra una excepción inesperada con una referencia corta y
     * legible, para poder correlacionar el log del servidor con el
     * reporte que el usuario pueda enviar desde la interfaz.
     */
    private function logAndRef(\Throwable $e, string $where): string
    {
        $ref = strtoupper(bin2hex(random_bytes(3)));
        log_message('critical', "[MensajesController::{$where}] ref={$ref} " . $e->getMessage() . "\n" . $e->getTraceAsString());
        return $ref;
    }

    private const ALLOWED_EXTENSIONS = [
        'jpg', 'jpeg', 'png', 'webp', 'gif',
        'pdf', 'doc', 'docx', 'xls', 'xlsx',
        'txt', 'mp4',
    ];

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

        // getClientExtension() no es de confianza (nombre puesto por el cliente):
        // se valida contra lista blanca para no poder guardar un .php camuflado
        // con un MIME permitido (p. ej. detectado como text/plain).
        $ext = strtolower($file->getClientExtension());
        if (!in_array($ext, self::ALLOWED_EXTENSIONS, true)) {
            return ['error' => 'Extensión de archivo no permitida.'];
        }

        $uploadDir = FCPATH . 'uploads/' . $subfolder . '/';
        $this->secureUploadDir($uploadDir);

        $newName = uniqid('', true) . '_' . time() . '.' . $ext;
        try {
            $file->move($uploadDir, $newName);
        } catch (\Throwable $e) {
            log_message('error', 'MensajesController::handleFileUpload move failed: ' . $e->getMessage());
            return ['error' => 'No se pudo guardar el archivo. Inténtalo de nuevo.'];
        }

        return [
            'path' => 'uploads/' . $subfolder . '/' . $newName,
            'name' => $file->getClientName(),
            'size' => $file->getSize(),
            'mime' => $mime,
        ];
    }
}
