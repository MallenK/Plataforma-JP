<?php

namespace App\Controllers;

use App\Models\PlayerBonoModel;
use App\Models\BonoTypeModel;
use App\Models\UserModel;

class BonosController extends BaseController
{
    protected PlayerBonoModel $bonoModel;
    protected BonoTypeModel   $typeModel;
    protected UserModel       $userModel;

    public function initController(
        \CodeIgniter\HTTP\RequestInterface  $request,
        \CodeIgniter\HTTP\ResponseInterface $response,
        \Psr\Log\LoggerInterface            $logger
    ) {
        parent::initController($request, $response, $logger);
        $this->bonoModel = new PlayerBonoModel();
        $this->typeModel = new BonoTypeModel();
        $this->userModel = new UserModel();
    }

    // ────────────────────────────────────────────────────────────────
    //  Lista + estadísticas
    // ────────────────────────────────────────────────────────────────

    public function index()
    {
        $filter = $this->request->getGet('filtro') ?? 'activos';

        $bonos = match($filter) {
            'todos'         => $this->bonoModel->getAllWithDetails(),
            'vencidos'      => $this->getExpiredBonos(),
            'sin-asignar'   => $this->getUnassignedBonos(),
            'agotados'      => $this->getDepletedBonos(),
            'casi-agotados' => $this->getLowSessionBonos(),
            default         => $this->bonoModel->getActiveBonosWithDetails(),
        };

        return view('bonos/index', [
            'title'        => 'Bonos — JP Preparation',
            'pageTitle'    => 'Bonos',
            'pageSubtitle' => 'Membresías y bonos de entrenamiento',
            'bonos'        => $bonos,
            'stats'        => $this->bonoModel->getStats(),
            'bonoTypes'    => $this->typeModel->getActive(),
            'players'      => $this->userModel->where('role', 'player')->where('status', 'active')->orderBy('name')->findAll(),
            'filtro'       => $filter,
        ]);
    }

    // ────────────────────────────────────────────────────────────────
    //  Crear bono (player_id opcional)
    // ────────────────────────────────────────────────────────────────

    public function store()
    {
        $playerIdRaw = $this->request->getPost('player_id');
        $playerId    = ($playerIdRaw !== '' && $playerIdRaw !== null) ? (int)$playerIdRaw : null;
        $bonoTypeId  = (int)$this->request->getPost('bono_type_id');
        $startDate   = $this->request->getPost('start_date') ?: date('Y-m-d');
        $notes       = $this->request->getPost('notes') ?: null;

        if (!$bonoTypeId) {
            session()->setFlashdata('error', 'El tipo de bono es obligatorio.');
            return redirect()->to('/bonos');
        }

        $willBeQueued = $playerId && $this->bonoModel->hasActiveBono($playerId);

        $type = $this->typeModel->find($bonoTypeId);
        if (!$type) {
            session()->setFlashdata('error', 'Tipo de bono no encontrado.');
            return redirect()->to('/bonos');
        }

        $expiresAt = !empty($type['validity_days'])
            ? date('Y-m-d', strtotime($startDate . ' +' . $type['validity_days'] . ' days'))
            : null;

        $this->bonoModel->insert([
            'player_id'          => $playerId,
            'bono_type_id'       => $bonoTypeId,
            'sessions_total'     => (int)$type['sessions'],
            'sessions_remaining' => (int)$type['sessions'],
            'start_date'         => $startDate,
            'expires_at'         => $expiresAt,
            'notes'              => $notes,
            'created_by'         => $this->currentUserId(),
        ]);

        if (!$playerId) {
            $msg = 'Bono creado sin jugador asignado. Puedes asignarlo desde el detalle.';
        } elseif ($willBeQueued) {
            $msg = 'Bono creado y encolado: se activará automáticamente cuando el alumno agote o caduque su bono actual.';
        } else {
            $msg = 'Bono emitido correctamente.';
        }

        session()->setFlashdata('success', $msg);
        return redirect()->to('/bonos');
    }

    // ────────────────────────────────────────────────────────────────
    //  Detalle de un bono
    // ────────────────────────────────────────────────────────────────

    public function show(int $id)
    {
        $bono = $this->getBonoWithDetails($id);
        if (!$bono) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        $history = !empty($bono['player_id'])
            ? $this->bonoModel->getBonosForPlayer((int)$bono['player_id'])
            : [];

        return view('bonos/show', [
            'title'   => 'Bono — JP Preparation',
            'bono'    => $bono,
            'history' => $history,
            'players' => $this->userModel->where('role', 'player')->where('status', 'active')->orderBy('name')->findAll(),
        ]);
    }

    // ────────────────────────────────────────────────────────────────
    //  Asignar jugador a un bono sin asignar
    // ────────────────────────────────────────────────────────────────

    public function assign(int $id)
    {
        $bono = $this->bonoModel->find($id);
        if (!$bono) {
            session()->setFlashdata('error', 'Bono no encontrado.');
            return redirect()->to('/bonos');
        }

        if (!empty($bono['player_id'])) {
            session()->setFlashdata('error', 'Este bono ya tiene un jugador asignado.');
            return redirect()->to('/bonos/' . $id);
        }

        $playerId = (int)$this->request->getPost('player_id');
        if (!$playerId) {
            session()->setFlashdata('error', 'Debes seleccionar un jugador.');
            return redirect()->to('/bonos/' . $id);
        }

        $willBeQueued = $this->bonoModel->hasActiveBono($playerId);

        $this->bonoModel->update($id, ['player_id' => $playerId]);

        $msg = $willBeQueued
            ? 'Jugador asignado. El bono queda encolado tras el activo actual del alumno.'
            : 'Jugador asignado al bono correctamente.';

        session()->setFlashdata('success', $msg);
        return redirect()->to('/bonos/' . $id);
    }

    // ────────────────────────────────────────────────────────────────
    //  Editar bono
    // ────────────────────────────────────────────────────────────────

    public function update(int $id)
    {
        $bono = $this->bonoModel->find($id);
        if (!$bono) {
            session()->setFlashdata('error', 'Bono no encontrado.');
            return redirect()->to('/bonos');
        }

        $data = [];

        if ($this->request->getPost('notes') !== null) {
            $data['notes'] = $this->request->getPost('notes') ?: null;
        }
        if ($this->request->getPost('sessions_remaining') !== null) {
            $data['sessions_remaining'] = max(0, (int)$this->request->getPost('sessions_remaining'));
        }
        if ($this->request->getPost('expires_at') !== null) {
            $data['expires_at'] = $this->request->getPost('expires_at') ?: null;
        }

        if (!empty($data)) {
            $this->bonoModel->update($id, $data);
        }

        session()->setFlashdata('success', 'Bono actualizado.');
        return redirect()->to('/bonos/' . $id);
    }

    // ────────────────────────────────────────────────────────────────
    //  Eliminar bono
    // ────────────────────────────────────────────────────────────────

    public function destroy(int $id)
    {
        $this->bonoModel->delete($id);
        session()->setFlashdata('success', 'Bono eliminado.');
        return redirect()->to('/bonos');
    }

    // ────────────────────────────────────────────────────────────────
    //  AJAX: comprobar si el jugador ya tiene bono activo
    // ────────────────────────────────────────────────────────────────

    public function checkActive()
    {
        $playerId = (int)$this->request->getPost('player_id');
        if (!$playerId) {
            return $this->response->setJSON(['has_active' => false, 'bono' => null]);
        }

        $bono = $this->bonoModel->getActiveBono($playerId);

        return $this->response->setJSON([
            'has_active' => $bono !== null,
            'bono'       => $bono,
        ]);
    }

    // ────────────────────────────────────────────────────────────────
    //  Helpers privados
    // ────────────────────────────────────────────────────────────────

    private function getExpiredBonos(): array
    {
        $today = date('Y-m-d');
        $db    = \Config\Database::connect();

        return $db->table('player_bonos pb')
            ->select('pb.*, u.name AS player_name, u.email AS player_email, u.avatar AS player_avatar, bt.name AS bono_name, bt.sessions AS bono_sessions_original')
            ->join('users u',       'u.id = pb.player_id', 'left')
            ->join('bono_types bt', 'bt.id = pb.bono_type_id')
            ->groupStart()
                ->where('pb.sessions_remaining', 0)
                ->orWhere('pb.expires_at <', $today)
            ->groupEnd()
            ->orderBy('pb.created_at', 'DESC')
            ->get()->getResultArray();
    }

    private function getUnassignedBonos(): array
    {
        $db = \Config\Database::connect();

        return $db->table('player_bonos pb')
            ->select('pb.*, NULL AS player_name, NULL AS player_email, NULL AS player_avatar, bt.name AS bono_name, bt.sessions AS bono_sessions_original')
            ->join('bono_types bt', 'bt.id = pb.bono_type_id')
            ->where('pb.player_id IS NULL')
            ->orderBy('pb.created_at', 'DESC')
            ->get()->getResultArray();
    }

    /**
     * Bonos asignados a un alumno con 0 sesiones restantes — necesitan renovación.
     */
    private function getDepletedBonos(): array
    {
        $db = \Config\Database::connect();

        return $db->table('player_bonos pb')
            ->select('pb.*, u.name AS player_name, u.email AS player_email, u.avatar AS player_avatar, bt.name AS bono_name, bt.sessions AS bono_sessions_original')
            ->join('users u',       'u.id = pb.player_id')
            ->join('bono_types bt', 'bt.id = pb.bono_type_id')
            ->where('pb.sessions_remaining', 0)
            ->orderBy('pb.updated_at', 'DESC')
            ->get()->getResultArray();
    }

    /**
     * Bonos asignados con exactamente 1 sesión restante (alerta).
     */
    private function getLowSessionBonos(): array
    {
        $today = date('Y-m-d');
        $db    = \Config\Database::connect();

        return $db->table('player_bonos pb')
            ->select('pb.*, u.name AS player_name, u.email AS player_email, u.avatar AS player_avatar, bt.name AS bono_name, bt.sessions AS bono_sessions_original')
            ->join('users u',       'u.id = pb.player_id')
            ->join('bono_types bt', 'bt.id = pb.bono_type_id')
            ->where('pb.sessions_remaining', 1)
            ->groupStart()
                ->where('pb.expires_at IS NULL')
                ->orWhere('pb.expires_at >=', $today)
            ->groupEnd()
            ->orderBy('pb.updated_at', 'DESC')
            ->get()->getResultArray();
    }

    private function getBonoWithDetails(int $id): ?array
    {
        $db  = \Config\Database::connect();
        $row = $db->table('player_bonos pb')
            ->select('pb.*, u.name AS player_name, u.email AS player_email, u.avatar AS player_avatar, bt.name AS bono_name, bt.sessions AS bono_sessions_original, bt.price AS bono_price, u2.name AS created_by_name')
            ->join('users u',       'u.id = pb.player_id', 'left')
            ->join('bono_types bt', 'bt.id = pb.bono_type_id')
            ->join('users u2',      'u2.id = pb.created_by', 'left')
            ->where('pb.id', $id)
            ->get()->getRowArray();

        return $row ?: null;
    }
}
