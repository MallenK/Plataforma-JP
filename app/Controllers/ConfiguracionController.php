<?php

namespace App\Controllers;

use App\Services\ConfiguracionService;

class ConfiguracionController extends BaseController
{
    protected ConfiguracionService $cfgService;

    public function initController(
        \CodeIgniter\HTTP\RequestInterface  $request,
        \CodeIgniter\HTTP\ResponseInterface $response,
        \Psr\Log\LoggerInterface            $logger
    ) {
        parent::initController($request, $response, $logger);
        $this->cfgService = new ConfiguracionService();
    }

    // ────────────────────────────────────────────────────────────────
    //  Página principal
    // ────────────────────────────────────────────────────────────────

    public function index(?int $legacyId = null)
    {
        $isAdmin      = $this->isAdmin();
        $isSuperAdmin = $this->isSuperAdmin();

        $settings  = $this->cfgService->getAllSettings();
        $locations = $isAdmin ? $this->cfgService->getLocations()    : [];
        $staff     = $isAdmin ? $this->cfgService->getStaffUsers()   : [];
        $bonoTypes = $isAdmin ? $this->cfgService->getBonoTypes()    : [];
        $logs      = $isAdmin ? $this->cfgService->getRecentLogs(50) : [];

        return view('configuracion/index', [
            'title'        => 'Configuración — JP Preparation',
            'settings'     => $settings,
            'locations'    => $locations,
            'staff'        => $staff,
            'bonoTypes'    => $bonoTypes,
            'logs'         => $logs,
            'isAdmin'      => $isAdmin,
            'isSuperAdmin' => $isSuperAdmin,
            'currentUserId'=> $this->currentUserId(),
            'section'      => $this->request->getGet('section') ?? 'general',
        ]);
    }

    // ════════════════════════════════════════════════════════════════
    //  GENERAL
    // ════════════════════════════════════════════════════════════════

    public function saveGeneral()
    {
        $this->cfgService->saveSettings([
            'academy_name'     => $this->request->getPost('academy_name')     ?? '',
            'academy_email'    => $this->request->getPost('academy_email')    ?? '',
            'academy_phone'    => $this->request->getPost('academy_phone')    ?? '',
            'academy_language' => $this->request->getPost('academy_language') ?? 'es',
            'academy_timezone' => $this->request->getPost('academy_timezone') ?? 'Europe/Madrid',
            'academy_currency' => $this->request->getPost('academy_currency') ?? 'EUR',
            'academy_location' => $this->request->getPost('academy_location') ?? '',
            'academy_website'  => $this->request->getPost('academy_website')  ?? '',
        ], $this->currentUserId());

        session()->setFlashdata('success', 'Configuración general guardada correctamente.');
        return redirect()->to('/configuracion?section=general');
    }

    // ════════════════════════════════════════════════════════════════
    //  GESTIÓN DE STAFF
    // ════════════════════════════════════════════════════════════════

    public function createStaff()
    {
        $result = $this->cfgService->createStaffUser([
            'name'  => $this->request->getPost('name'),
            'email' => $this->request->getPost('email'),
            'role'  => $this->request->getPost('role'),
        ]);

        if (!$result['success']) {
            $errors = isset($result['errors']) ? implode(' ', $result['errors']) : ($result['error'] ?? 'Error desconocido');
            session()->setFlashdata('error', $errors);
        } else {
            session()->setFlashdata('staff_created_name',     $result['name']);
            session()->setFlashdata('staff_created_password', $result['password']);
        }

        return redirect()->to('/configuracion?section=staff');
    }

    public function updateStaffRole(int $id)
    {
        $role      = $this->request->getPost('role');
        $currentId = (int)$this->currentUserId();
        $ok        = $this->cfgService->updateStaffRole($id, $role, $currentId);

        session()->setFlashdata(
            $ok ? 'success' : 'error',
            $ok ? 'Rol actualizado correctamente.' : 'No se pudo cambiar el rol.'
        );

        return redirect()->to('/configuracion?section=staff');
    }

    public function deactivateStaff(int $id)
    {
        $ok = $this->cfgService->deactivateStaffUser($id, (int)$this->currentUserId());

        session()->setFlashdata(
            $ok ? 'success' : 'error',
            $ok ? 'Usuario desactivado correctamente.' : 'No se pudo desactivar el usuario.'
        );

        return redirect()->to('/configuracion?section=staff');
    }

    public function activateStaff(int $id)
    {
        $ok = $this->cfgService->activateStaffUser($id);

        session()->setFlashdata(
            $ok ? 'success' : 'error',
            $ok ? 'Usuario reactivado correctamente.' : 'No se pudo reactivar el usuario.'
        );

        return redirect()->to('/configuracion?section=staff');
    }

    public function deleteStaff(int $id)
    {
        $result = $this->cfgService->deleteStaffUser($id, (int)$this->currentUserId());

        session()->setFlashdata(
            $result['success'] ? 'success' : 'error',
            $result['success']
                ? 'El usuario "' . $result['name'] . '" ha sido eliminado permanentemente.'
                : ($result['error'] ?? 'No se pudo eliminar el usuario.')
        );

        return redirect()->to('/configuracion?section=staff');
    }

    // ════════════════════════════════════════════════════════════════
    //  CAMPOS Y SEDES
    // ════════════════════════════════════════════════════════════════

    public function createSede()
    {
        $result = $this->cfgService->createLocation($this->request->getPost());

        if (!$result['success']) {
            $errors = isset($result['errors']) ? implode(' ', $result['errors']) : 'Error al crear la sede.';
            session()->setFlashdata('error', $errors);
        } else {
            session()->setFlashdata('success', 'Sede creada correctamente.');
        }

        return redirect()->to('/configuracion?section=sedes');
    }

    public function updateSede(int $id)
    {
        $ok = $this->cfgService->updateLocation($id, $this->request->getPost());

        session()->setFlashdata(
            $ok ? 'success' : 'error',
            $ok ? 'Sede actualizada correctamente.' : 'No se pudo actualizar la sede.'
        );

        return redirect()->to('/configuracion?section=sedes');
    }

    public function deleteSede(int $id)
    {
        $ok = $this->cfgService->deleteLocation($id);

        session()->setFlashdata(
            $ok ? 'success' : 'error',
            $ok ? 'Sede eliminada correctamente.' : 'No se pudo eliminar la sede.'
        );

        return redirect()->to('/configuracion?section=sedes');
    }

    // ════════════════════════════════════════════════════════════════
    //  FACTURACIÓN — TIPOS DE BONO
    // ════════════════════════════════════════════════════════════════

    public function createBonoType()
    {
        $result = $this->cfgService->createBonoType($this->request->getPost());

        if (!$result['success']) {
            $errors = isset($result['errors']) ? implode(' ', $result['errors']) : 'Error al crear el tipo de bono.';
            session()->setFlashdata('error', $errors);
        } else {
            session()->setFlashdata('success', 'Tipo de bono creado correctamente.');
        }

        return redirect()->to('/configuracion?section=facturacion');
    }

    public function updateBonoType(int $id)
    {
        $ok = $this->cfgService->updateBonoType($id, $this->request->getPost());

        session()->setFlashdata(
            $ok ? 'success' : 'error',
            $ok ? 'Tipo de bono actualizado correctamente.' : 'No se pudo actualizar el tipo de bono.'
        );

        return redirect()->to('/configuracion?section=facturacion');
    }

    public function deleteBonoType(int $id)
    {
        $ok = $this->cfgService->deleteBonoType($id);

        session()->setFlashdata(
            $ok ? 'success' : 'error',
            $ok ? 'Tipo de bono eliminado correctamente.' : 'No se pudo eliminar el tipo de bono.'
        );

        return redirect()->to('/configuracion?section=facturacion');
    }

    // ════════════════════════════════════════════════════════════════
    //  SEGURIDAD
    // ════════════════════════════════════════════════════════════════

    public function saveSeguridad()
    {
        $minPass = max(6, (int)($this->request->getPost('sec_min_password') ?? 8));
        $timeout = max(5, (int)($this->request->getPost('sec_session_timeout') ?? 10));

        $this->cfgService->saveSettings([
            'sec_min_password'   => (string)$minPass,
            'sec_require_upper'  => $this->request->getPost('sec_require_upper')   ? '1' : '0',
            'sec_require_numbers'=> $this->request->getPost('sec_require_numbers') ? '1' : '0',
            'sec_require_special'=> $this->request->getPost('sec_require_special') ? '1' : '0',
            'sec_session_timeout'=> (string)$timeout,
        ], $this->currentUserId());

        session()->setFlashdata('success', 'Configuración de seguridad guardada correctamente.');
        return redirect()->to('/configuracion?section=seguridad');
    }

    // ════════════════════════════════════════════════════════════════
    //  WEB PÚBLICA
    // ════════════════════════════════════════════════════════════════

    public function saveWeb()
    {
        $this->cfgService->saveSettings([
            'web_active'    => $this->request->getPost('web_active')    ? '1' : '0',
            'web_instagram' => $this->request->getPost('web_instagram') ?? '',
            'web_twitter'   => $this->request->getPost('web_twitter')   ?? '',
            'web_facebook'  => $this->request->getPost('web_facebook')  ?? '',
            'web_youtube'   => $this->request->getPost('web_youtube')   ?? '',
            'web_tiktok'    => $this->request->getPost('web_tiktok')    ?? '',
        ], $this->currentUserId());

        session()->setFlashdata('success', 'Configuración de la web pública guardada correctamente.');
        return redirect()->to('/configuracion?section=web');
    }
}
