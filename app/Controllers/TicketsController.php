<?php

namespace App\Controllers;

use App\Models\TicketModel;
use App\Models\TicketReplyModel;
use App\Models\TicketAttachmentModel;
use App\Models\NotificationModel;
use App\Models\UserModel;

class TicketsController extends BaseController
{
    private TicketModel           $ticketModel;
    private TicketReplyModel      $replyModel;
    private TicketAttachmentModel $attachModel;
    private NotificationModel     $notifModel;
    private UserModel             $userModel;

    public function initController(\CodeIgniter\HTTP\RequestInterface $request,
                                   \CodeIgniter\HTTP\ResponseInterface $response,
                                   \Psr\Log\LoggerInterface $logger): void
    {
        parent::initController($request, $response, $logger);
        $this->ticketModel = new TicketModel();
        $this->replyModel  = new TicketReplyModel();
        $this->attachModel = new TicketAttachmentModel();
        $this->notifModel  = new NotificationModel();
        $this->userModel   = new UserModel();
    }

    // ─────────────────────────────────────────────────────────
    // USUARIO — lista de sus propios tickets
    // ─────────────────────────────────────────────────────────

    public function index(): string
    {
        $userId  = $this->currentUserId();
        $tickets = $this->ticketModel->getForUser($userId);

        return view('tickets/index', [
            'title'      => 'Mis Tickets',
            'tickets'    => $tickets,
            'categories' => TicketModel::CATEGORIES,
            'priorities' => TicketModel::PRIORITIES,
            'statuses'   => TicketModel::STATUSES,
        ]);
    }

    // ─────────────────────────────────────────────────────────
    // USUARIO — formulario de creación
    // ─────────────────────────────────────────────────────────

    public function create(): string
    {
        return view('tickets/create', [
            'title'      => 'Nuevo Ticket',
            'categories' => TicketModel::CATEGORIES,
            'priorities' => TicketModel::PRIORITIES,
        ]);
    }

    // ─────────────────────────────────────────────────────────
    // USUARIO — guardar nuevo ticket
    // ─────────────────────────────────────────────────────────

    public function store(): \CodeIgniter\HTTP\ResponseInterface
    {
        $userId = $this->currentUserId();

        $title       = trim($this->request->getPost('title') ?? '');
        $description = trim($this->request->getPost('description') ?? '');
        $category    = $this->request->getPost('category');
        $priority    = $this->request->getPost('priority');

        if (!$title || !$description || !$category || !$priority) {
            return $this->response->setJSON(['error' => 'Todos los campos son obligatorios.'])->setStatusCode(422);
        }

        if (!array_key_exists($category, TicketModel::CATEGORIES)) {
            return $this->response->setJSON(['error' => 'Categoría no válida.'])->setStatusCode(422);
        }

        if (!array_key_exists($priority, TicketModel::PRIORITIES)) {
            return $this->response->setJSON(['error' => 'Prioridad no válida.'])->setStatusCode(422);
        }

        $ticketId = $this->ticketModel->createTicket([
            'user_id'     => $userId,
            'title'       => $title,
            'description' => $description,
            'category'    => $category,
            'priority'    => $priority,
        ]);

        if (!$ticketId) {
            return $this->response->setJSON(['error' => 'Error al crear el ticket.'])->setStatusCode(500);
        }

        // Adjunto opcional
        $file = $this->request->getFile('attachment');
        if ($file && $file->isValid() && !$file->hasMoved()) {
            $result = $this->handleFileUpload($file);
            if (!isset($result['error'])) {
                $this->attachModel->addAttachment($ticketId, null, $result);
            }
        }

        // Notificar a todos los superadmins
        $this->notifyAdmins($ticketId, $title, $userId);

        $ticket = $this->ticketModel->find($ticketId);

        return $this->response->setJSON([
            'ok'            => true,
            'ticket_number' => $ticket['ticket_number'],
            'redirect'      => base_url('tickets/' . $ticketId),
            'csrf'          => csrf_hash(),
        ]);
    }

    // ─────────────────────────────────────────────────────────
    // USUARIO — detalle de un ticket
    // ─────────────────────────────────────────────────────────

    public function show(int $id): mixed
    {
        $userId = $this->currentUserId();
        $role   = $this->currentRole();

        $ticket = $this->ticketModel->getWithUser($id);
        if (!$ticket) {
            return $this->response->setStatusCode(404);
        }

        // El usuario solo puede ver sus propios tickets; superadmin ve todos
        if ($ticket['user_id'] !== $userId && $role !== 'superadmin') {
            return $this->response->setStatusCode(403);
        }

        $replies     = $this->replyModel->getForTicket($id);
        $attachments = $this->attachModel->getForTicket($id);

        // Adjuntos por reply
        $replyAttachments = [];
        foreach ($replies as $reply) {
            $replyAttachments[$reply['id']] = $this->attachModel->getForReply($reply['id']);
        }

        return view('tickets/show', [
            'title'            => 'Ticket ' . $ticket['ticket_number'],
            'ticket'           => $ticket,
            'replies'          => $replies,
            'attachments'      => $attachments,
            'replyAttachments' => $replyAttachments,
            'categories'       => TicketModel::CATEGORIES,
            'priorities'       => TicketModel::PRIORITIES,
            'statuses'         => TicketModel::STATUSES,
            'isSuperAdmin'     => $role === 'superadmin',
        ]);
    }

    // ─────────────────────────────────────────────────────────
    // ADMIN — lista completa de tickets
    // ─────────────────────────────────────────────────────────

    public function adminIndex(): string
    {
        $filters = [
            'status'   => $this->request->getGet('status')   ?? '',
            'priority' => $this->request->getGet('priority') ?? '',
            'category' => $this->request->getGet('category') ?? '',
            'search'   => $this->request->getGet('search')   ?? '',
        ];

        $page    = max(1, (int) ($this->request->getGet('page') ?? 1));
        $perPage = 20;
        $offset  = ($page - 1) * $perPage;

        $tickets = $this->ticketModel->getAll($filters, $perPage, $offset);
        $total   = $this->ticketModel->countAll($filters);

        return view('tickets/admin/index', [
            'title'      => 'Gestión de Tickets',
            'tickets'    => $tickets,
            'total'      => $total,
            'page'       => $page,
            'perPage'    => $perPage,
            'filters'    => $filters,
            'categories' => TicketModel::CATEGORIES,
            'priorities' => TicketModel::PRIORITIES,
            'statuses'   => TicketModel::STATUSES,
        ]);
    }

    // ─────────────────────────────────────────────────────────
    // ADMIN — dashboard de estadísticas
    // ─────────────────────────────────────────────────────────

    public function dashboard(): string
    {
        return view('tickets/admin/dashboard', [
            'title' => 'Dashboard de Tickets',
            'stats' => $this->ticketModel->getStats(),
            'categories' => TicketModel::CATEGORIES,
            'priorities' => TicketModel::PRIORITIES,
            'statuses'   => TicketModel::STATUSES,
        ]);
    }

    // ─────────────────────────────────────────────────────────
    // ADMIN — responder ticket
    // ─────────────────────────────────────────────────────────

    public function reply(int $id): \CodeIgniter\HTTP\ResponseInterface
    {
        $ticket = $this->ticketModel->find($id);
        if (!$ticket) {
            return $this->response->setJSON(['error' => 'Ticket no encontrado.'])->setStatusCode(404);
        }

        $body = trim($this->request->getPost('body') ?? '');
        if (!$body) {
            return $this->response->setJSON(['error' => 'La respuesta no puede estar vacía.'])->setStatusCode(422);
        }

        $replyId = $this->replyModel->createReply($id, $this->currentUserId(), $body);

        if (!$replyId) {
            return $this->response->setJSON(['error' => 'Error al guardar la respuesta.'])->setStatusCode(500);
        }

        // Adjunto opcional en la respuesta
        $file = $this->request->getFile('attachment');
        if ($file && $file->isValid() && !$file->hasMoved()) {
            $result = $this->handleFileUpload($file);
            if (!isset($result['error'])) {
                $this->attachModel->addAttachment($id, $replyId, $result);
            }
        }

        // Si el ticket estaba abierto, pasarlo a en progreso automáticamente
        if ($ticket['status'] === 'abierto') {
            $this->ticketModel->updateStatus($id, 'en_progreso');
        }

        // Notificar al creador del ticket
        $this->notifyTicketOwner($ticket, 'respuesta');

        $reply = $this->replyModel->getForTicket($id);
        $lastReply = end($reply);

        return $this->response->setJSON([
            'ok'   => true,
            'csrf' => csrf_hash(),
            'reply' => $lastReply,
        ]);
    }

    // ─────────────────────────────────────────────────────────
    // ADMIN — cambiar estado
    // ─────────────────────────────────────────────────────────

    public function updateStatus(int $id): \CodeIgniter\HTTP\ResponseInterface
    {
        $ticket = $this->ticketModel->find($id);
        if (!$ticket) {
            return $this->response->setJSON(['error' => 'Ticket no encontrado.'])->setStatusCode(404);
        }

        $status = $this->request->getPost('status');
        if (!array_key_exists($status, TicketModel::STATUSES)) {
            return $this->response->setJSON(['error' => 'Estado no válido.'])->setStatusCode(422);
        }

        $this->ticketModel->updateStatus($id, $status);

        // Notificar al creador cuando cambia estado
        $this->notifyTicketOwner($ticket, 'estado', $status);

        return $this->response->setJSON([
            'ok'     => true,
            'status' => $status,
            'label'  => TicketModel::STATUSES[$status],
            'csrf'   => csrf_hash(),
        ]);
    }

    // ─────────────────────────────────────────────────────────
    // ADMIN + USUARIO — cambiar prioridad
    // El creador puede cambiar prioridad solo si el ticket está abierto
    // ─────────────────────────────────────────────────────────

    public function updatePriority(int $id): \CodeIgniter\HTTP\ResponseInterface
    {
        $userId = $this->currentUserId();
        $role   = $this->currentRole();

        $ticket = $this->ticketModel->find($id);
        if (!$ticket) {
            return $this->response->setJSON(['error' => 'Ticket no encontrado.'])->setStatusCode(404);
        }

        // Usuario normal solo puede cambiar prioridad si el ticket es suyo y está abierto
        if ($role !== 'superadmin') {
            if ($ticket['user_id'] !== $userId) {
                return $this->response->setJSON(['error' => 'Sin permisos.'])->setStatusCode(403);
            }
            if (!in_array($ticket['status'], ['abierto', 'en_progreso'])) {
                return $this->response->setJSON(['error' => 'No puedes modificar un ticket resuelto o cerrado.'])->setStatusCode(403);
            }
        }

        $priority = $this->request->getPost('priority');
        if (!array_key_exists($priority, TicketModel::PRIORITIES)) {
            return $this->response->setJSON(['error' => 'Prioridad no válida.'])->setStatusCode(422);
        }

        $this->ticketModel->updatePriority($id, $priority);

        return $this->response->setJSON([
            'ok'       => true,
            'priority' => $priority,
            'label'    => TicketModel::PRIORITIES[$priority],
            'csrf'     => csrf_hash(),
        ]);
    }

    // ─────────────────────────────────────────────────────────
    // DESCARGAR adjunto
    // ─────────────────────────────────────────────────────────

    public function download(int $attachId): mixed
    {
        $userId = $this->currentUserId();
        $role   = $this->currentRole();

        $attach = $this->attachModel->find($attachId);
        if (!$attach) {
            return $this->response->setStatusCode(404);
        }

        $ticket = $this->ticketModel->find($attach['ticket_id']);
        if (!$ticket) {
            return $this->response->setStatusCode(404);
        }

        // Solo el creador o superadmin puede descargar
        if ($ticket['user_id'] !== $userId && $role !== 'superadmin') {
            return $this->response->setStatusCode(403);
        }

        $fullPath = FCPATH . $attach['file_path'];
        if (!file_exists($fullPath)) {
            return $this->response->setStatusCode(404);
        }

        return $this->response->download($fullPath, null)->setFileName($attach['file_name']);
    }

    // ─────────────────────────────────────────────────────────
    // Helpers privados
    // ─────────────────────────────────────────────────────────

    private function handleFileUpload(\CodeIgniter\HTTP\Files\UploadedFile $file): array
    {
        $maxSize = 10 * 1024 * 1024; // 10 MB
        $allowed = [
            'image/jpeg', 'image/png', 'image/webp', 'image/gif',
            'application/pdf', 'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'text/plain', 'video/mp4',
        ];

        if ($file->getSize() > $maxSize) {
            return ['error' => 'El archivo supera el límite de 10 MB.'];
        }

        if (!in_array($file->getMimeType(), $allowed)) {
            return ['error' => 'Tipo de archivo no permitido.'];
        }

        $uploadDir = FCPATH . 'uploads/tickets/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $newName = uniqid('', true) . '_' . time() . '.' . $file->getClientExtension();
        $file->move($uploadDir, $newName);

        return [
            'path' => 'uploads/tickets/' . $newName,
            'name' => $file->getClientName(),
            'size' => $file->getSize(),
            'mime' => $file->getMimeType(),
        ];
    }

    private function notifyAdmins(int $ticketId, string $ticketTitle, int $fromUserId): void
    {
        $ticket    = $this->ticketModel->find($ticketId);
        $superadmins = $this->userModel
            ->where('role', 'superadmin')
            ->where('id !=', $fromUserId)
            ->where('status', 'active')
            ->select('id')
            ->findAll();

        $ids = array_column($superadmins, 'id');
        if (empty($ids)) return;

        $this->notifModel->createWithRecipients([
            'sender_id'  => $fromUserId,
            'type'       => 'individual',
            'title'      => 'Nuevo ticket: ' . $ticket['ticket_number'],
            'body'       => $ticketTitle,
            'created_at' => date('Y-m-d H:i:s'),
        ], $ids);
    }

    private function notifyTicketOwner(array $ticket, string $event, string $extraInfo = ''): void
    {
        $adminId = $this->currentUserId();

        if ($ticket['user_id'] === $adminId) return;

        if ($event === 'respuesta') {
            $title = 'Respuesta a tu ticket ' . $ticket['ticket_number'];
            $body  = 'Un administrador ha respondido a tu ticket: ' . $ticket['title'];
        } else {
            $label = TicketModel::STATUSES[$extraInfo] ?? $extraInfo;
            $title = 'Ticket ' . $ticket['ticket_number'] . ' actualizado';
            $body  = 'El estado de tu ticket ha cambiado a: ' . $label;
        }

        $this->notifModel->createWithRecipients([
            'sender_id'  => $adminId,
            'type'       => 'individual',
            'title'      => $title,
            'body'       => $body,
            'created_at' => date('Y-m-d H:i:s'),
        ], [$ticket['user_id']]);
    }
}
