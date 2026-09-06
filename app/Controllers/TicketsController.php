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
        // Prefill cuando se llega desde una alerta de error / permiso.
        $originIn = $this->request->getGet('origin');
        $origin   = in_array($originIn, TicketModel::ORIGINS, true) ? $originIn : 'manual';

        $prefill = null;
        if ($origin !== 'manual') {
            $prefill = [
                'origin'    => $origin,
                'ref'       => substr(preg_replace('/[^A-Z0-9]/i', '', (string) $this->request->getGet('ref')), 0, 12),
                'url'       => mb_substr((string) $this->request->getGet('url'), 0, 300),
                'message'   => mb_substr((string) $this->request->getGet('msg'), 0, 500),
                'category'  => $origin === 'permiso' ? 'consulta' : 'bug',
            ];
        }

        return view('tickets/create', [
            'title'      => 'Nuevo Ticket',
            'categories' => TicketModel::CATEGORIES,
            'priorities' => TicketModel::PRIORITIES,
            'prefill'    => $prefill,
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

        // Contexto de reporte (cuando el ticket nace de una alerta de error/permiso)
        $originIn  = $this->request->getPost('origin');
        $origin    = in_array($originIn, TicketModel::ORIGINS, true) ? $originIn : 'manual';
        $errorRef  = substr(preg_replace('/[^A-Z0-9]/i', '', (string) $this->request->getPost('error_ref')), 0, 12) ?: null;

        $context = null;
        if ($origin !== 'manual') {
            $ctxRaw  = json_decode((string) $this->request->getPost('context'), true);
            $context = json_encode([
                'url'        => is_array($ctxRaw) ? mb_substr((string) ($ctxRaw['url'] ?? ''), 0, 300) : null,
                'endpoint'   => is_array($ctxRaw) ? mb_substr((string) ($ctxRaw['endpoint'] ?? ''), 0, 200) : null,
                'message'    => is_array($ctxRaw) ? mb_substr((string) ($ctxRaw['message'] ?? ''), 0, 500) : null,
                'user_agent' => mb_substr((string) $this->request->getUserAgent()->getAgentString(), 0, 300),
                'client_ts'  => is_array($ctxRaw) ? mb_substr((string) ($ctxRaw['client_ts'] ?? ''), 0, 40) : null,
                'error_ref'  => $errorRef,
            ], JSON_UNESCAPED_UNICODE);
        }

        $ticketId = $this->ticketModel->createTicket([
            'user_id'     => $userId,
            'title'       => $title,
            'description' => $description,
            'category'    => $category,
            'priority'    => $priority,
            'origin'      => $origin,
            'error_ref'   => $errorRef,
            'context'     => $context,
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

        // Cada usuario ve sus propios tickets; los gestores (admin/superadmin) ven todos.
        $isManager = in_array($role, ['admin', 'superadmin'], true);
        if ($ticket['user_id'] !== $userId && !$isManager) {
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
            'isManager'        => $isManager,
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
    // EXPORTAR — CSV (Excel) o vista imprimible (PDF vía navegador)
    // ─────────────────────────────────────────────────────────

    /** Filtros de la query string, comunes a listado y exportación. */
    private function requestFilters(): array
    {
        return [
            'status'   => $this->request->getGet('status')   ?? '',
            'priority' => $this->request->getGet('priority') ?? '',
            'category' => $this->request->getGet('category') ?? '',
            'search'   => $this->request->getGet('search')   ?? '',
        ];
    }

    /** Exporta los tickets del usuario que ha entrado. */
    public function export()
    {
        return $this->exportTickets(
            $this->ticketModel->getForUser((int) $this->currentUserId(), $this->requestFilters(), 5000),
            false
        );
    }

    /** Exporta TODOS los tickets del sistema (solo superadmin). */
    public function adminExport()
    {
        return $this->exportTickets(
            $this->ticketModel->getAll($this->requestFilters(), 5000),
            true
        );
    }

    private function exportTickets(array $tickets, bool $withUser)
    {
        $format = strtolower((string) ($this->request->getGet('format') ?? 'csv'));

        if ($format === 'pdf' || $format === 'print') {
            return view('tickets/export_print', [
                'title'      => 'Tickets — export',
                'tickets'    => $tickets,
                'withUser'   => $withUser,
                'filters'    => $this->requestFilters(),
                'autoPrint'  => true,
                'categories' => TicketModel::CATEGORIES,
                'priorities' => TicketModel::PRIORITIES,
                'statuses'   => TicketModel::STATUSES,
            ]);
        }

        // CSV (por defecto) — BOM UTF-8 para que Excel lo abra bien.
        $cats = TicketModel::CATEGORIES;
        $pris = TicketModel::PRIORITIES;
        $stas = TicketModel::STATUSES;

        $filename = 'tickets-' . date('Y-m-d_His') . '.csv';
        $fh = fopen('php://temp', 'r+');
        fwrite($fh, "\xEF\xBB\xBF");

        $header = ['Nº', 'Título', 'Categoría', 'Prioridad', 'Estado'];
        if ($withUser) {
            $header[] = 'Usuario';
        }
        $header = array_merge($header, ['Respuestas', 'Creado', 'Resuelto', 'Cerrado', 'Descripción']);
        fputcsv($fh, $header, ';');

        foreach ($tickets as $t) {
            $row = [
                $t['ticket_number'],
                $t['title'],
                $cats[$t['category']] ?? $t['category'],
                $pris[$t['priority']] ?? $t['priority'],
                $stas[$t['status']] ?? $t['status'],
            ];
            if ($withUser) {
                $row[] = $t['user_name'] ?? '';
            }
            $row = array_merge($row, [
                (int) ($t['reply_count'] ?? 0),
                $t['created_at'] ?? '',
                $t['resolved_at'] ?? '',
                $t['closed_at'] ?? '',
                preg_replace('/\s+/', ' ', (string) ($t['description'] ?? '')),
            ]);
            fputcsv($fh, $row, ';');
        }

        rewind($fh);
        $csv = stream_get_contents($fh);
        fclose($fh);

        return $this->response
            ->setHeader('Content-Type', 'text/csv; charset=UTF-8')
            ->setHeader('Content-Disposition', 'attachment; filename="' . $filename . '"')
            ->setBody($csv);
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

        // Los gestores (admin/superadmin) cambian cualquier prioridad.
        // El dueño solo la de su ticket y solo si sigue abierto / en progreso.
        if (!in_array($role, ['admin', 'superadmin'], true)) {
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

        // Solo el creador o un gestor (admin/superadmin) puede descargar
        if ($ticket['user_id'] !== $userId && !in_array($role, ['admin', 'superadmin'], true)) {
            return $this->response->setStatusCode(403);
        }

        helper('upload');
        $fullPath = upload_resolve_stored($attach['file_path']);
        if ($fullPath === null) {
            return $this->response->setStatusCode(404);
        }

        return $this->response->download($fullPath, null)->setFileName($attach['file_name']);
    }

    // ─────────────────────────────────────────────────────────
    // Helpers privados
    // ─────────────────────────────────────────────────────────

    /** Extensiones permitidas en adjuntos de ticket (lista blanca real). */
    private const ALLOWED_EXTENSIONS = [
        'jpg', 'jpeg', 'png', 'webp', 'gif',
        'pdf', 'doc', 'docx', 'xls', 'xlsx',
        'txt', 'mp4',
    ];

    private function handleFileUpload(\CodeIgniter\HTTP\Files\UploadedFile $file): array
    {
        helper('upload');

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

        // La extensión del cliente no es de confianza: lista blanca obligatoria
        // (el MIME por sí solo no frena un polyglot GIF89a + <?php …).
        $ext = upload_allowed_extension($file->getClientExtension(), self::ALLOWED_EXTENSIONS);
        if ($ext === null) {
            return ['error' => 'Extensión de archivo no permitida.'];
        }

        // Fuera del webroot: solo se sirve por TicketsController::download().
        $uploadDir = upload_private_dir('tickets');
        upload_harden_dir($uploadDir);

        $newName = bin2hex(random_bytes(16)) . '.' . $ext;
        $file->move($uploadDir, $newName);

        return [
            'path' => upload_stored_path('tickets', $newName),
            'name' => $file->getClientName(),
            'size' => $file->getSize(),
            'mime' => $file->getMimeType(),
        ];
    }

    private function notifyAdmins(int $ticketId, string $ticketTitle, int $fromUserId): void
    {
        $ticket   = $this->ticketModel->find($ticketId);
        $managers = $this->userModel
            ->whereIn('role', ['admin', 'superadmin'])
            ->where('id !=', $fromUserId)
            ->where('status', 'active')
            ->select('id')
            ->findAll();

        $ids = array_column($managers, 'id');
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
