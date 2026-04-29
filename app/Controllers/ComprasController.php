<?php

namespace App\Controllers;

use App\Models\PurchaseRequestModel;

class ComprasController extends BaseController
{
    private PurchaseRequestModel $model;

    public function initController(
        \CodeIgniter\HTTP\RequestInterface  $request,
        \CodeIgniter\HTTP\ResponseInterface $response,
        \Psr\Log\LoggerInterface            $logger
    ): void {
        parent::initController($request, $response, $logger);
        $this->model = new PurchaseRequestModel();
    }

    public function index(): string
    {
        $filtro = $this->request->getGet('filtro') ?? 'todos';
        $status = $filtro !== 'todos' ? $filtro : null;

        return view('compras/index', [
            'pageTitle'     => 'Compras',
            'pageSubtitle'  => 'Lista de solicitudes de compra',
            'requests'      => $this->model->getAllWithDetails($status),
            'stats'         => $this->model->getStats(),
            'filtro'        => $filtro,
            'currentUserId' => $this->currentUserId(),
            'currentRole'   => $this->currentRole(),
        ]);
    }

    public function store(): \CodeIgniter\HTTP\ResponseInterface
    {
        $name        = trim($this->request->getPost('name') ?? '');
        $description = trim($this->request->getPost('description') ?? '');
        $url         = trim($this->request->getPost('url') ?? '');
        $price       = $this->request->getPost('price') ?: null;
        $category    = $this->request->getPost('category') ?: 'otros';
        $priority    = $this->request->getPost('priority') ?: 'media';

        if (!$name) {
            session()->setFlashdata('error', 'El nombre del producto es obligatorio.');
            return redirect()->to('/compras');
        }

        $this->model->insert([
            'name'         => $name,
            'description'  => $description ?: null,
            'url'          => $url ?: null,
            'price'        => $price,
            'category'     => $category,
            'priority'     => $priority,
            'status'       => 'pendiente',
            'requested_by' => $this->currentUserId(),
            'created_at'   => date('Y-m-d H:i:s'),
            'updated_at'   => date('Y-m-d H:i:s'),
        ]);

        session()->setFlashdata('success', 'Solicitud añadida correctamente.');
        return redirect()->to('/compras');
    }

    public function updateStatus(int $id): \CodeIgniter\HTTP\ResponseInterface
    {
        if (!$this->isAdmin()) {
            session()->setFlashdata('error', 'Sin permisos para esta acción.');
            return redirect()->to('/compras');
        }

        $validStatuses = ['pendiente', 'en_revision', 'aprobado', 'denegado', 'comprado', 'cancelado'];
        $status        = $this->request->getPost('status');
        $comment       = trim($this->request->getPost('admin_comment') ?? '');

        if (!in_array($status, $validStatuses)) {
            session()->setFlashdata('error', 'Estado no válido.');
            return redirect()->to('/compras');
        }

        $this->model->update($id, [
            'status'        => $status,
            'admin_comment' => $comment ?: null,
            'reviewed_by'   => $this->currentUserId(),
            'reviewed_at'   => date('Y-m-d H:i:s'),
            'updated_at'    => date('Y-m-d H:i:s'),
        ]);

        session()->setFlashdata('success', 'Estado actualizado correctamente.');
        return redirect()->to('/compras');
    }

    public function destroy(int $id): \CodeIgniter\HTTP\ResponseInterface
    {
        if (!$this->isAdmin()) {
            session()->setFlashdata('error', 'Sin permisos para esta acción.');
            return redirect()->to('/compras');
        }

        $this->model->delete($id);
        session()->setFlashdata('success', 'Solicitud eliminada.');
        return redirect()->to('/compras');
    }
}
