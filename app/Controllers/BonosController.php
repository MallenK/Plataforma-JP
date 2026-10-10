<?php

namespace App\Controllers;

use App\Models\PlayerBonoModel;
use App\Models\BonoTypeModel;
use App\Models\UserModel;
use App\Services\AuditService;
use App\Services\BonoControlService;
use App\Services\BonoCoverageService;
use App\Services\BonoLedgerService;
use App\Services\ClasesService;

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
            'anulados'      => $this->getVoidedBonos(),
            'casi-agotados' => $this->getLowSessionBonos(),
            default         => $this->bonoModel->getActiveBonosWithDetails(),
        };

        $playerIds = array_values(array_unique(array_filter(array_map(fn($b) => (int) ($b['player_id'] ?? 0), $bonos))));

        return view('bonos/index', [
            'overbooked'   => (new BonoCoverageService())->overbooked($playerIds),
            'title'        => 'Bonos — JP Preparation',
            'pageTitle'    => 'Bonos',
            'pageSubtitle' => 'Membresías y bonos de entrenamiento',
            'bonos'        => $bonos,
            'stats'        => $this->bonoModel->getStats(),
            'bonoTypes'    => $this->typeModel->getActive(),
            'allBonoTypes' => $this->typeModel->where('archived_at IS NULL')->orderBy('active', 'DESC')->orderBy('name', 'ASC')->findAll(),
            'players'      => $this->userModel->where('role', 'player')->where('status', 'active')->orderBy('name')->findAll(),
            'filtro'       => $filter,
            'debtCount'    => count((new BonoControlService())->openDebts()),
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

        $hadOtherBonos = $playerId && count($this->bonoModel->getUsableBonos($playerId)) > 0;

        $type = $this->typeModel->find($bonoTypeId);
        if (!$type) {
            session()->setFlashdata('error', 'Tipo de bono no encontrado.');
            return redirect()->to('/bonos');
        }

        $expiresAt = !empty($type['validity_days'])
            ? date('Y-m-d', strtotime($startDate . ' +' . $type['validity_days'] . ' days'))
            : null;

        $row = [
            'player_id'          => $playerId,
            'bono_type_id'       => $bonoTypeId,
            'sessions_total'     => (int)$type['sessions'],
            'sessions_remaining' => (int)$type['sessions'],
            'start_date'         => $startDate,
            'expires_at'         => $expiresAt,
            'notes'              => $notes,
            'created_by'         => $this->currentUserId(),
        ] + BonoControlService::priceSnapshot($type);   // v1.33.0: precio congelado
        $this->bonoModel->insert($row);
        $newBonoId = (int) $this->bonoModel->getInsertID();
        AuditService::record('player_bono', $newBonoId, AuditService::CREATE, null, $row, null, (int) $this->currentUserId());

        // TICKET-013: libro de movimientos. Las clases dadas sin bono NO se saldan
        // solas: se avisa y se saldan a mano desde la ficha del bono.
        $settledMsg = '';
        if ($playerId) {
            BonoLedgerService::log($playerId, BonoLedgerService::GRANTED, (int)$type['sessions'], $newBonoId, null, $type['name'] ?? null);
            $settledMsg = $this->pendingDebtsHint($playerId);
        }

        if (!$playerId) {
            $msg = 'Bono creado sin jugador asignado. Puedes asignarlo desde el detalle.';
        } elseif ($hadOtherBonos) {
            $msg = 'Bono creado. El alumno tiene varios bonos con saldo: al pasar lista se elige de cuál se descuenta cada sesión.';
        } else {
            $msg = 'Bono emitido correctamente.';
        }

        session()->setFlashdata('success', $msg . $settledMsg);
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

        $control   = new BonoControlService();
        $movements = !empty($bono['player_id']) ? (new BonoLedgerService())->forPlayer((int)$bono['player_id'], 60) : [];
        $debts     = !empty($bono['player_id']) ? $control->openDebts((int)$bono['player_id']) : [];

        $overbooked = !empty($bono['player_id'])
            ? ((new BonoCoverageService())->overbooked([(int) $bono['player_id']])[(int) $bono['player_id']] ?? null)
            : null;

        $upcoming = !empty($bono['player_id'])
            ? (new ClasesService())->getUpcomingSessionsForPlayer((int) $bono['player_id'], 10)
            : ['items' => [], 'total' => 0];

        return view('bonos/show', [
            'upcoming'   => $upcoming,
            'overbooked' => $overbooked,
            'title'     => 'Bono — JP Preparation',
            'bono'      => $bono,
            'movements' => $movements,
            'debts'     => $debts,
            'history'   => $history,
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

        if (!empty($bono['voided_at'])) {
            session()->setFlashdata('error', 'Este bono está anulado y no se puede asignar.');
            return redirect()->to('/bonos/' . $id);
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

        $hadOtherBonos = count($this->bonoModel->getUsableBonos($playerId)) > 0;

        $this->bonoModel->update($id, ['player_id' => $playerId]);
        BonoLedgerService::log($playerId, BonoLedgerService::ASSIGNED, (int)$bono['sessions_remaining'], $id, null, 'Bono sin dueño asignado al alumno');
        $settledMsg = $this->pendingDebtsHint($playerId);

        $msg = $hadOtherBonos
            ? 'Jugador asignado. El alumno ya tenía otro bono con saldo: al pasar lista se elige de cuál se descuenta cada sesión.'
            : 'Jugador asignado al bono correctamente.';

        session()->setFlashdata('success', $msg . $settledMsg);
        return redirect()->to('/bonos/' . $id);
    }

    // ────────────────────────────────────────────────────────────────
    //  Editar bono
    // ────────────────────────────────────────────────────────────────

    public function update(int $id)
    {
        // v1.33.0: cambiar saldo o caducidad exige motivo y queda en el libro y en auditoría.
        $res = (new BonoControlService())->updateBono($id, [
            'notes'              => $this->request->getPost('notes'),
            'sessions_remaining' => $this->request->getPost('sessions_remaining'),
            'expires_at'         => $this->request->getPost('expires_at'),
        ], $this->request->getPost('reason'), (int) $this->currentUserId());

        if (!$res['success']) {
            session()->setFlashdata('error', $res['error']);
        } else {
            session()->setFlashdata('success', ($res['changed'] ?? 0) > 0 ? 'Bono actualizado.' : 'No había cambios que guardar.');
        }
        return redirect()->to('/bonos/' . $id);
    }

    // ────────────────────────────────────────────────────────────────
    //  Anular bono (v1.33.0 «Nada se borra»: ya no se elimina)
    // ────────────────────────────────────────────────────────────────

    public function void(int $id)
    {
        $res = (new BonoControlService())->voidBono($id, $this->request->getPost('reason'), (int) $this->currentUserId());

        if (!$res['success']) {
            session()->setFlashdata('error', $res['error']);
        } else {
            $n = (int) ($res['cancelled'] ?? 0);
            session()->setFlashdata('success', 'Bono anulado.' . ($n > 0 ? " Se han cancelado las {$n} sesiones que quedaban." : '') . ' Su historial se conserva.');
        }
        return redirect()->to('/bonos/' . $id);
    }

    /** Ruta antigua `bonos/:id/delete` (pestañas abiertas de antes): ahora anula, con motivo. */
    public function destroy(int $id)
    {
        return $this->void($id);
    }

    // ────────────────────────────────────────────────────────────────
    //  Ampliar caducidad (TICKET-013): 15 / 30 / 60 días o fecha personalizada
    // ────────────────────────────────────────────────────────────────

    public function extend(int $id)
    {
        $mode = (string) $this->request->getPost('mode');
        $res  = (new BonoControlService())->extendBono(
            $id,
            $mode === 'custom' ? 'custom' : (int) $mode,
            $this->request->getPost('custom_date') ?: null,
            (int) $this->currentUserId()
        );

        session()->setFlashdata(
            $res['success'] ? 'success' : 'error',
            $res['success'] ? 'Caducidad ampliada hasta el ' . date('d/m/Y', strtotime($res['date'])) . '.' : $res['error']
        );
        return redirect()->to('/bonos/' . $id);
    }

    // ────────────────────────────────────────────────────────────────
    //  Deudas de sesión y sesiones "no reflejadas" (TICKET-013)
    // ────────────────────────────────────────────────────────────────

    /** GET /bonos/informe — informe por alumno (saldo, importes, alertas). Solo administración. */
    public function informe()
    {
        return view('bonos/informe', [
            'title'  => 'Informe de bonos — JP Preparation',
            'report' => (new \App\Services\BonoReportService())->build(),
        ]);
    }

    public function deudas()
    {
        $control = new BonoControlService();

        // Bonos con saldo de cada alumno con deudas: se elige con cuál saldar cada clase.
        $debts   = $control->openDebts();
        $usables = [];
        foreach ($debts as &$d) {
            $uid = (int) $d['user_id'];
            $usables[$uid] ??= $this->bonoModel->getUsableBonos($uid);
            $d['usable_bonos'] = $usables[$uid];
        }
        unset($d);

        return view('bonos/deudas', [
            'title'        => 'Deudas de sesión — JP Preparation',
            'pageTitle'    => 'Deudas de sesión',
            'pageSubtitle' => 'Clases dadas sin bono y sesiones anteriores al control',
            'debts'        => $debts,
            'unreflected'  => $control->unreflected(),
            'since'        => (new BonoCoverageService())->controlSince(),
        ]);
    }

    public function resolveDebt(int $cspId)
    {
        $res = (new BonoControlService())->resolveDebt(
            $cspId,
            (string) $this->request->getPost('resolution'),
            (int) $this->currentUserId(),
            trim((string) $this->request->getPost('note')) ?: null
        );

        session()->setFlashdata(
            $res['success'] ? 'success' : 'error',
            $res['success'] ? 'Deuda resuelta y registrada.' : $res['error']
        );
        return redirect()->to('/bonos/deudas');
    }

    /**
     * Aviso (NO acción) para el flash: el alumno tiene clases dadas sin bono.
     * Saldarlas es una decisión manual, con confirmación, desde la ficha del bono.
     */
    private function pendingDebtsHint(int $playerId): string
    {
        $n = count((new BonoControlService())->openDebts($playerId));
        return $n > 0
            ? ' El alumno tiene ' . $n . ' clase(s) dadas sin bono: puedes saldarlas con un bono desde su ficha (no se descuentan solas).'
            : '';
    }

    /** POST /bonos/:id/saldar-deudas — salda con ESTE bono las clases sin bono del alumno (tras confirmar). */
    public function saldarDeudas(int $id)
    {
        $bono = $this->bonoModel->find($id);
        if (!$bono || empty($bono['player_id'])) {
            session()->setFlashdata('error', 'Bono no encontrado o sin alumno asignado.');
            return redirect()->to('/bonos');
        }

        $r = (new BonoControlService())->settleWithBono((int) $bono['player_id'], (int) $this->currentUserId(), $id);
        if ($r['settled'] > 0) {
            session()->setFlashdata('success', 'Se han saldado ' . $r['settled'] . ' clase(s) sin bono con este bono'
                . ($r['remaining_debts'] > 0 ? ' (quedan ' . $r['remaining_debts'] . ' pendientes).' : '.'));
        } else {
            session()->setFlashdata('error', $r['remaining_debts'] > 0
                ? 'No se pudo saldar ninguna clase: el bono no tiene saldo o está caducado.'
                : 'El alumno no tiene clases sin bono pendientes.');
        }
        return redirect()->to('/bonos/' . $id);
    }

    /** POST /bonos/deudas/:csp/saldar — salda UNA clase con el bono elegido (tras confirmar). */
    public function settleDebt(int $cspId)
    {
        $bonoId = (int) $this->request->getPost('bono_id');
        $res = $bonoId > 0
            ? (new BonoControlService())->settleOne($cspId, $bonoId, (int) $this->currentUserId())
            : ['success' => false, 'error' => 'Elige con qué bono saldar la clase.'];

        session()->setFlashdata(
            $res['success'] ? 'success' : 'error',
            $res['success'] ? 'Clase saldada con el bono elegido.' : $res['error']
        );
        return redirect()->to('/bonos/deudas');
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

        $bono   = $this->bonoModel->getActiveBono($playerId);
        $usable = $this->bonoModel->getUsableBonos($playerId);

        // TICKET-013: clases ya dadas sin bono. Al emitir un bono se descuenta
        // 1 sesión por cada una, así que se avisa ANTES de crearlo.
        $debts = array_map(
            fn($d) => ['title' => $d['title'], 'date' => $d['session_date']],
            (new BonoControlService())->openDebts($playerId)
        );

        return $this->response->setJSON([
            'has_active' => $bono !== null,
            'bono'       => $bono,
            'bonos'      => $usable,
            'debts'      => $debts,
        ]);
    }

    // ────────────────────────────────────────────────────────────────
    //  Helpers privados
    // ────────────────────────────────────────────────────────────────

    // ────────────────────────────────────────────────────────────────
    //  Tipos de bono — CRUD (solo admin/superadmin)
    // ────────────────────────────────────────────────────────────────

    public function storeTipoBono()
    {
        $name         = trim($this->request->getPost('name') ?? '');
        $sessions     = (int)$this->request->getPost('sessions');
        $price        = (float)$this->request->getPost('price');
        $validityDays = (int)$this->request->getPost('validity_days');

        if (strlen($name) < 2 || $sessions < 1 || $validityDays < 1) {
            return $this->response->setJSON(['ok' => false, 'error' => 'Datos inválidos.']);
        }

        $id = $this->typeModel->insert([
            'name'          => $name,
            'sessions'      => $sessions,
            'price'         => max(0.0, $price),
            'validity_days' => $validityDays,
            'active'        => 1,
        ]);

        if (!$id) {
            return $this->response->setJSON(['ok' => false, 'error' => 'Error al crear el tipo de bono.']);
        }
        AuditService::record('bono_type', (int) $id, AuditService::CREATE, null,
            ['name' => $name, 'sessions' => $sessions, 'price' => max(0.0, $price), 'validity_days' => $validityDays]);

        return $this->response->setJSON([
            'ok'        => true,
            'tipo'      => [
                'id'            => $id,
                'name'          => $name,
                'sessions'      => $sessions,
                'price'         => number_format($price, 2),
                'validity_days' => $validityDays,
                'active'        => 1,
            ],
            'csrf_name' => csrf_token(),
            'csrf_hash' => csrf_hash(),
        ]);
    }

    public function updateTipoBono(int $id)
    {
        $tipo = $this->typeModel->find($id);
        if (!$tipo) {
            return $this->response->setJSON(['ok' => false, 'error' => 'Tipo no encontrado.']);
        }

        $name = trim($this->request->getPost('name') ?? '');
        if (strlen($name) < 2) {
            return $this->response->setJSON(['ok' => false, 'error' => 'El nombre debe tener al menos 2 caracteres.']);
        }

        $this->typeModel->update($id, ['name' => $name]);
        if ($name !== $tipo['name']) {
            AuditService::record('bono_type', $id, AuditService::UPDATE, ['name' => $tipo['name']], ['name' => $name]);
        }

        return $this->response->setJSON([
            'ok'        => true,
            'name'      => $name,
            'csrf_name' => csrf_token(),
            'csrf_hash' => csrf_hash(),
        ]);
    }

    public function toggleTipoBono(int $id)
    {
        $tipo = $this->typeModel->find($id);
        if (!$tipo) {
            return $this->response->setJSON(['ok' => false, 'error' => 'Tipo no encontrado.']);
        }

        $newState = $tipo['active'] ? 0 : 1;
        $this->typeModel->update($id, ['active' => $newState]);
        AuditService::record('bono_type', $id, AuditService::UPDATE, ['active' => (int) $tipo['active']], ['active' => $newState]);

        return $this->response->setJSON([
            'ok'        => true,
            'active'    => $newState,
            'csrf_name' => csrf_token(),
            'csrf_hash' => csrf_hash(),
        ]);
    }

    private function getExpiredBonos(): array
    {
        $today = date('Y-m-d');
        $db    = \Config\Database::connect();

        return $db->table('player_bonos pb')
            ->select('pb.*, u.name AS player_name, u.email AS player_email, u.avatar AS player_avatar, u.status AS player_status, bt.name AS bono_name, bt.sessions AS bono_sessions_original')
            ->join('users u',       'u.id = pb.player_id', 'left')
            ->join('bono_types bt', 'bt.id = pb.bono_type_id')
            ->groupStart()
                ->where('pb.sessions_remaining', 0)
                ->orWhere('pb.expires_at <', $today)
            ->groupEnd()
            ->where('pb.voided_at IS NULL')
            ->orderBy('pb.created_at', 'DESC')
            ->get()->getResultArray();
    }

    private function getUnassignedBonos(): array
    {
        $db = \Config\Database::connect();

        return $db->table('player_bonos pb')
            ->select('pb.*, NULL AS player_name, NULL AS player_email, NULL AS player_avatar, NULL AS player_status, bt.name AS bono_name, bt.sessions AS bono_sessions_original')
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
            ->select('pb.*, u.name AS player_name, u.email AS player_email, u.avatar AS player_avatar, u.status AS player_status, bt.name AS bono_name, bt.sessions AS bono_sessions_original')
            ->join('users u',       'u.id = pb.player_id')
            ->join('bono_types bt', 'bt.id = pb.bono_type_id')
            ->where('pb.sessions_remaining', 0)
            ->where('pb.voided_at IS NULL')
            ->orderBy('pb.updated_at', 'DESC')
            ->get()->getResultArray();
    }

    /** Bonos anulados (v1.33.0): siguen existiendo con su histórico. */
    private function getVoidedBonos(): array
    {
        return \Config\Database::connect()->table('player_bonos pb')
            ->select('pb.*, u.name AS player_name, u.email AS player_email, u.avatar AS player_avatar, u.status AS player_status, bt.name AS bono_name, bt.sessions AS bono_sessions_original')
            ->join('users u',       'u.id = pb.player_id', 'left')
            ->join('bono_types bt', 'bt.id = pb.bono_type_id')
            ->where('pb.voided_at IS NOT NULL')
            ->orderBy('pb.voided_at', 'DESC')
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
            ->select('pb.*, u.name AS player_name, u.email AS player_email, u.avatar AS player_avatar, u.status AS player_status, bt.name AS bono_name, bt.sessions AS bono_sessions_original')
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
            ->select('pb.*, u.name AS player_name, u.email AS player_email, u.avatar AS player_avatar, u.status AS player_status, bt.name AS bono_name, bt.sessions AS bono_sessions_original, bt.price AS bono_price, u2.name AS created_by_name')
            ->join('users u',       'u.id = pb.player_id', 'left')
            ->join('bono_types bt', 'bt.id = pb.bono_type_id')
            ->join('users u2',      'u2.id = pb.created_by', 'left')
            ->where('pb.id', $id)
            ->get()->getRowArray();

        return $row ?: null;
    }
}
