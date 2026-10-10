<?php

namespace App\Controllers;

use App\Services\AuditService;
use App\Services\BonoControlService;
use App\Services\ExpenseService;
use App\Services\FinanceReportService;
use App\Services\FinanceService;
use App\Services\RevisionService;

/**
 * Finanzas — solo superadmin y admin. Plan: docs/finanzas/PLAN-finanzas-v2.md.
 * Controller fino: toda la lógica vive en FinanceService, ExpenseService,
 * FinanceReportService y RevisionService.
 */
class FinanzasController extends BaseController
{
    private function period(): array
    {
        return FinanceReportService::period(
            $this->request->getGet('mes'),
            $this->request->getGet('desde'),
            $this->request->getGet('hasta')
        );
    }

    private function base(string $tab, array $data = []): array
    {
        return $data + [
            'tab'     => $tab,
            'title'   => 'Finanzas — JP Preparation',
            'period'  => $this->period(),
            'months'  => FinanceReportService::lastMonths(18),
            'reviewCount' => $this->reviewCount(),
        ];
    }

    private function reviewCount(): int
    {
        try {
            $c = (new RevisionService())->counts();
            return (int) $c['unclosed'] + (int) $c['debts'] + (int) $c['pre_control'] + (int) $c['estimated'];
        } catch (\Throwable $e) {
            return 0;
        }
    }

    /** Vuelta a una pantalla de Finanzas indicada por el formulario (nunca fuera de /finanzas/). */
    private function safeBack(string $default): string
    {
        $b = (string) $this->request->getPost('back');
        return preg_match('#^/finanzas/[a-z0-9/_-]*$#i', $b) ? $b : $default;
    }

    private function back(string $to, array $res, string $ok)
    {
        session()->setFlashdata($res['success'] ? 'success' : 'error', $res['success'] ? $ok : ($res['error'] ?? 'No se pudo completar.'));
        return redirect()->to($to);
    }

    // ────────────────────────────────────────────────────────────────
    //  Pestañas
    // ────────────────────────────────────────────────────────────────

    public function index()
    {
        return redirect()->to('/finanzas/resumen');
    }

    public function resumen()
    {
        $p   = $this->period();
        $rep = new FinanceReportService();
        return view('finanzas/resumen', $this->base('resumen', [
            'summary' => $rep->summary($p['from'], $p['to']),
            'monthly' => $rep->monthly(12),
            'review'  => (new RevisionService())->counts(),
        ]));
    }

    public function movimientos()
    {
        $p    = $this->period();
        $type = (string) $this->request->getGet('tipo');
        $type = array_key_exists($type, FinanceReportService::MOVE_TYPES) ? $type : '';
        $mov  = (new FinanceReportService())->movements($p['from'], $p['to'], $type ?: null);

        if ($this->request->getGet('export') === 'csv') {
            return $this->csv('movimientos_' . $p['from'] . '_' . $p['to'] . '.csv',
                ['Fecha', 'Tipo', 'Alumno / quién', 'Detalle', 'Importe (€)', 'Anulado', 'Motivo anulación', 'Ref.'],
                array_map(fn($r) => [
                    $r['date'], FinanceReportService::MOVE_TYPES[$r['type']], $r['who'], $r['detail'],
                    number_format($r['cents'] / 100, 2, ',', ''), $r['voided'] ? 'sí' : '', $r['void_reason'] ?? '', $r['ref'],
                ], $mov['rows']));
        }
        return view('finanzas/movimientos', $this->base('movimientos', ['mov' => $mov, 'type' => $type]));
    }

    public function cobros()
    {
        $p   = $this->period();
        $fin = new FinanceService();
        $db  = \Config\Database::connect();
        $payments = $db->table('fin_payments p')
            ->select('p.*, u.name AS player_name, m.name AS method_name, cb.name AS created_by_name')
            ->join('users u', 'u.id = p.player_id', 'left')
            ->join('fin_payment_methods m', 'm.id = p.method_id', 'left')
            ->join('users cb', 'cb.id = p.created_by', 'left')
            ->where('p.paid_at >=', $p['from'])->where('p.paid_at <=', $p['to'])
            ->orderBy('p.paid_at', 'DESC')->orderBy('p.id', 'DESC')->get()->getResultArray();
        $debtors = array_values(array_filter($fin->playersOverview(), fn($r) => (int) $r['due'] > 0));

        return view('finanzas/cobros', $this->base('cobros', [
            'payments' => $payments,
            'debtors'  => $debtors,
            'methods'  => $fin->methods(),
            'players'  => $db->table('users')->select('id, name, status')->where('role', 'player')->orderBy('name')->get()->getResultArray(),
            'preselect'=> (int) $this->request->getGet('alumno'),
        ]));
    }

    public function gastos()
    {
        $p   = $this->period();
        $fin = new FinanceService();
        $db  = \Config\Database::connect();
        $cat = (int) $this->request->getGet('categoria') ?: null;
        return view('finanzas/gastos', $this->base('gastos', [
            'expenses'   => (new ExpenseService())->list($p['from'], $p['to'], $cat),
            'categories' => $fin->categories('expense'),
            'methods'    => $fin->methods(),
            'staff'      => $db->table('users')->select('id, name, role')->whereIn('role', ['coach', 'staff', 'admin', 'superadmin'])
                                ->where('status', 'active')->orderBy('name')->get()->getResultArray(),
            'locations'  => $db->table('locations')->select('id, name')->where('archived_at IS NULL')->orderBy('name')->get()->getResultArray(),
            'categoryId' => $cat,
        ]));
    }

    public function alumnos()
    {
        $q    = trim((string) $this->request->getGet('q'));
        $only = (string) $this->request->getGet('ver');
        $rows = (new FinanceService())->playersOverview();
        if ($q !== '') {
            $rows = array_values(array_filter($rows, fn($r) => mb_stripos($r['name'], $q) !== false));
        }
        if ($only === 'deben') {
            $rows = array_values(array_filter($rows, fn($r) => (int) $r['due'] > 0));
        } elseif ($only === 'favor') {
            $rows = array_values(array_filter($rows, fn($r) => (int) $r['due'] < 0));
        }
        return view('finanzas/alumnos', $this->base('alumnos', ['rows' => $rows, 'q' => $q, 'only' => $only]));
    }

    public function alumno(int $playerId)
    {
        $db     = \Config\Database::connect();
        $player = $db->table('users')->select('id, name, email, status, created_at')->where('id', $playerId)->where('role', 'player')->get()->getRowArray();
        if (!$player) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }
        $fin = new FinanceService();
        $today = date('Y-m-d');
        $bonos = $db->table('player_bonos pb')
            ->select('pb.*, bt.name AS bono_name')
            ->join('bono_types bt', 'bt.id = pb.bono_type_id', 'left')
            ->where('pb.player_id', $playerId)->orderBy('pb.created_at', 'DESC')->get()->getResultArray();
        $classes = $db->query(
            "SELECT cs.id, cs.session_date, cs.start_time, cs.status, csp.attendance, csp.bono_deducted_at, csp.bono_resolution,
                    bt.name AS bono_name, pb.price_cents / NULLIF(pb.sessions_total, 0) AS unit_cents
             FROM class_session_players csp
             JOIN class_sessions cs ON cs.id = csp.session_id
             LEFT JOIN player_bonos pb ON pb.id = csp.bono_deducted_from_id
             LEFT JOIN bono_types bt ON bt.id = pb.bono_type_id
             WHERE csp.user_id = ? ORDER BY cs.session_date DESC, cs.start_time DESC LIMIT 60",
            [$playerId]
        )->getResultArray();

        return view('finanzas/alumno', $this->base('alumnos', [
            'player'    => $player,
            'account'   => $fin->account($playerId),
            'bonos'     => $bonos,
            'classes'   => $classes,
            'debts'     => (new BonoControlService())->openDebts($playerId),
            'methods'   => $fin->methods(),
            'pending'   => $fin->pendingCharges($playerId),
            'today'     => $today,
            'incomeCategories' => $fin->categories('income'),
        ]));
    }

    /** Historial completo del alumno: todo lo que ha pasado con él en la plataforma. Descargable. */
    public function historial(int $playerId)
    {
        $db     = \Config\Database::connect();
        $player = $db->table('users')->select('id, name, email, status')->where('id', $playerId)->where('role', 'player')->get()->getRowArray();
        if (!$player) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }
        $okDate = static fn($d) => is_string($d) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) ? $d : null;
        $from   = $okDate($this->request->getGet('desde'));
        $to     = $okDate($this->request->getGet('hasta'));
        $cat    = (string) $this->request->getGet('categoria');
        $cat    = array_key_exists($cat, \App\Services\StudentHistoryService::CATEGORIES) ? $cat : '';

        $rows = (new \App\Services\StudentHistoryService())->timeline($playerId, $from, $to);
        $counts = array_fill_keys(array_keys(\App\Services\StudentHistoryService::CATEGORIES), 0);
        foreach ($rows as $r) {
            $counts[$r['cat']]++;
        }
        if ($cat !== '') {
            $rows = array_values(array_filter($rows, fn($r) => $r['cat'] === $cat));
        }

        AuditService::record('user', $playerId, AuditService::VIEW, null, null,
            'Historial completo consultado' . ($this->request->getGet('export') === 'csv' ? ' y descargado' : ''));

        if ($this->request->getGet('export') === 'csv') {
            $slug = trim(preg_replace('/[^a-z0-9]+/', '-', strtolower(iconv('UTF-8', 'ASCII//TRANSLIT', $player['name']) ?: 'alumno')), '-');
            $csv  = \App\Services\StudentHistoryService::toCsvRows($rows);
            return $this->csv('historial_' . $slug . '_' . date('Y-m-d') . '.csv', array_shift($csv), $csv);
        }

        return view('finanzas/historial', $this->base('alumnos', [
            'player' => $player, 'rows' => $rows, 'counts' => $counts,
            'from' => $from, 'to' => $to, 'cat' => $cat,
        ]));
    }

    public function entrenadores()
    {
        $p = $this->period();
        return view('finanzas/entrenadores', $this->base('entrenadores', [
            'coaches' => (new FinanceReportService())->coaches($p['from'], $p['to']),
        ]));
    }

    public function analisis()
    {
        $p = $this->period();
        return view('finanzas/analisis', $this->base('analisis', [
            'an' => (new FinanceReportService())->analysis($p['from'], $p['to']),
        ]));
    }

    public function config()
    {
        $fin = new FinanceService();
        $db  = \Config\Database::connect();
        $settings = [];
        foreach ($db->table('academy_settings')->whereIn('setting_key', ['fin_notice_hours', 'fin_auto_deduct', 'fin_tax_enabled'])->get()->getResultArray() as $s) {
            $settings[$s['setting_key']] = $s['setting_value'];
        }
        return view('finanzas/config', $this->base('config', [
            'methods'    => $fin->methods(false),
            'categories' => $fin->categories(null, false),
            'settings'   => $settings,
        ]));
    }

    public function revision()
    {
        $rev     = new RevisionService();
        $control = new BonoControlService();

        return view('finanzas/revision', $this->base('revision', [
            'title'      => 'Finanzas · Revisión — JP Preparation',
            'counts'     => $rev->counts(),
            'unclosed'   => $rev->unclosedSessions(),
            'debts'      => $control->openDebts(),
            'preControl' => $control->unreflected(),
            'estimated'  => $rev->estimatedPriceBonos(),
            'unused'     => $rev->expiredUnused(),
            'since'      => (new \App\Services\BonoCoverageService())->controlSince(),
            'closePreview' => $rev->initialClose(false),
        ]));
    }

    // ────────────────────────────────────────────────────────────────
    //  Ayuda: manual de uso (pantalla, versión imprimible, capturas y PDF)
    // ────────────────────────────────────────────────────────────────

    private const MANUAL_DIR = APPPATH . 'Data/manual_finanzas/';
    private const MANUAL_PDF = 'Manual-Finanzas-JP-Preparation.pdf';

    public function ayuda()
    {
        return view('finanzas/ayuda', $this->base('ayuda', ['hasPdf' => is_file(self::MANUAL_DIR . self::MANUAL_PDF)]));
    }

    public function ayudaImprimir()
    {
        return view('finanzas/manual_print', ['date' => date('d/m/Y')]);
    }

    /** Capturas del manual (solo admin, nunca públicas). */
    public function manualImg(string $name)
    {
        if (!preg_match('/^[a-z0-9_-]+\.png$/', $name) || !is_file(self::MANUAL_DIR . $name)) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }
        return $this->response->setHeader('Content-Type', 'image/png')
            ->setHeader('Cache-Control', 'private, max-age=86400')
            ->setHeader('X-Content-Type-Options', 'nosniff')
            ->setBody(file_get_contents(self::MANUAL_DIR . $name));
    }

    public function manualPdf()
    {
        $f = self::MANUAL_DIR . self::MANUAL_PDF;
        if (!is_file($f)) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }
        return $this->response->download($f, null)->setFileName(self::MANUAL_PDF);
    }

    /** Ruta antigua `/pendientes` (v1.33.0 en desarrollo) → Finanzas › Revisión. */
    public function legacyPendientes()
    {
        return redirect()->to('/finanzas/revision', 301);
    }

    // ────────────────────────────────────────────────────────────────
    //  Acciones
    // ────────────────────────────────────────────────────────────────

    public function storePayment()
    {
        $playerId = (int) $this->request->getPost('player_id');
        $res = (new FinanceService())->registerPayment(
            $playerId,
            (int) RevisionService::parseEuroToCents($this->request->getPost('amount')),
            (int) $this->request->getPost('method_id') ?: null,
            $this->request->getPost('paid_at'),
            (int) $this->request->getPost('charge_id') ?: null,
            $this->request->getPost('reference'),
            $this->request->getPost('note'),
            (int) $this->currentUserId()
        );
        $ok = 'Cobro registrado.' . (!empty($res['credit']) ? ' Sobran ' . number_format($res['credit'] / 100, 2, ',', '.') . ' €, que quedan a favor del alumno.' : '');
        $back = $this->request->getPost('back') === 'alumno' ? '/finanzas/alumnos/' . $playerId : '/finanzas/cobros';
        return $this->back($back, $res, $ok);
    }

    public function voidPayment(int $id)
    {
        $res = (new FinanceService())->voidPayment($id, $this->request->getPost('reason'), (int) $this->currentUserId());
        return $this->back($this->safeBack('/finanzas/cobros'), $res, 'Cobro anulado. Queda en el historial.');
    }

    public function storeCharge(int $playerId)
    {
        $fin   = new FinanceService();
        $list  = RevisionService::parseEuroToCents($this->request->getPost('amount'));
        $concept = trim((string) $this->request->getPost('concept'));
        if ($list === null || $list <= 0 || mb_strlen($concept) < 2) {
            return $this->back('/finanzas/alumnos/' . $playerId, ['success' => false, 'error' => 'Indica concepto e importe.'], '');
        }
        $disc = FinanceService::parseDiscount($this->request->getPost('discount'), $list);
        $fin->createCharge($playerId, [
            'category_id'     => (int) $this->request->getPost('category_id') ?: null,
            'concept'         => $concept,
            'list_cents'      => $list,
            'discount_cents'  => $disc,
            'discount_reason' => $disc > 0 ? (trim((string) $this->request->getPost('discount_reason')) ?: 'Descuento') : null,
            'amount_cents'    => $list - $disc,
            'charged_at'      => $this->request->getPost('charged_at') ?: date('Y-m-d'),
        ], (int) $this->currentUserId());
        return $this->back('/finanzas/alumnos/' . $playerId, ['success' => true], 'Cargo añadido.');
    }

    public function voidCharge(int $id)
    {
        $res = (new FinanceService())->voidCharge($id, $this->request->getPost('reason'), (int) $this->currentUserId());
        return $this->back($this->safeBack('/finanzas/alumnos'), $res, 'Cargo anulado. Queda en el historial.');
    }

    public function storeExpense()
    {
        $res = (new ExpenseService())->create($this->request->getPost(), $this->request->getFile('attachment'), (int) $this->currentUserId());
        return $this->back('/finanzas/gastos', $res, 'Gasto registrado.');
    }

    public function voidExpense(int $id)
    {
        $res = (new ExpenseService())->void($id, $this->request->getPost('reason'), (int) $this->currentUserId());
        return $this->back('/finanzas/gastos', $res, 'Gasto anulado. Queda en el historial.');
    }

    public function expenseAttachment(int $id)
    {
        helper('upload');
        $e = (new ExpenseService())->find($id);
        $full = $e ? upload_resolve_stored($e['attachment_path'] ?? null) : null;
        if (!$full) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }
        $this->response->setHeader('X-Content-Type-Options', 'nosniff');
        return $this->response->download($full, null)->setFileName($e['attachment_name'] ?: basename($full));
    }

    public function saveConfig()
    {
        $db  = \Config\Database::connect();
        $now = date('Y-m-d H:i:s');
        $vals = [
            'fin_notice_hours' => (string) min(168, max(1, (int) $this->request->getPost('fin_notice_hours'))),
            'fin_auto_deduct'  => $this->request->getPost('fin_auto_deduct') ? '1' : '0',
        ];
        foreach ($vals as $k => $v) {
            $old = $db->table('academy_settings')->select('setting_value')->where('setting_key', $k)->get()->getRow();
            if ($old) {
                $db->table('academy_settings')->where('setting_key', $k)->update(['setting_value' => $v, 'updated_at' => $now]);
            } else {
                $db->table('academy_settings')->insert(['setting_key' => $k, 'setting_value' => $v, 'setting_type' => 'string', 'updated_at' => $now]);
            }
            if (($old->setting_value ?? null) !== $v) {
                AuditService::record('setting', null, AuditService::UPDATE, [$k => $old->setting_value ?? null], [$k => $v]);
            }
        }
        return $this->back('/finanzas/configuracion', ['success' => true], 'Ajustes guardados.');
    }

    public function saveCatalog(string $kind)
    {
        $db    = \Config\Database::connect();
        $table = $kind === 'metodo' ? 'fin_payment_methods' : 'fin_categories';
        $name  = trim((string) $this->request->getPost('name'));
        $id    = (int) $this->request->getPost('id');
        $action = (string) $this->request->getPost('action');

        if ($id && $action === 'archive') {
            $db->table($table)->where('id', $id)->update(['active' => 0, 'archived_at' => date('Y-m-d H:i:s')]);
            AuditService::record($table, $id, AuditService::ARCHIVE);
            return $this->back('/finanzas/configuracion', ['success' => true], 'Archivado. Lo ya registrado lo conserva.');
        }
        if (mb_strlen($name) < 2) {
            return $this->back('/finanzas/configuracion', ['success' => false, 'error' => 'Indica un nombre.'], '');
        }
        if ($id) {
            $before = $db->table($table)->where('id', $id)->get()->getRowArray();
            $db->table($table)->where('id', $id)->update(['name' => mb_substr($name, 0, 80)]);
            AuditService::record($table, $id, AuditService::UPDATE, ['name' => $before['name'] ?? null], ['name' => $name]);
            return $this->back('/finanzas/configuracion', ['success' => true], 'Guardado.');
        }
        $row = ['name' => mb_substr($name, 0, 80), 'sort' => 50, 'created_at' => date('Y-m-d H:i:s')];
        if ($kind === 'metodo') {
            $row['code'] = substr(preg_replace('/[^a-z0-9]+/', '_', strtolower(iconv('UTF-8', 'ASCII//TRANSLIT', $name) ?: $name)), 0, 24) . '_' . substr(bin2hex(random_bytes(2)), 0, 4);
        } else {
            $row['kind'] = $this->request->getPost('kind') === 'income' ? 'income' : 'expense';
        }
        $db->table($table)->insert($row);
        AuditService::record($table, (int) $db->insertID(), AuditService::CREATE, null, $row);
        return $this->back('/finanzas/configuracion', ['success' => true], 'Añadido.');
    }

    /** Cierre de revisión inicial: da por bueno lo anterior en bloque (RevisionService::initialClose). */
    public function initialClose()
    {
        $r = (new RevisionService())->initialClose(true, (int) $this->currentUserId());
        session()->setFlashdata('success', sprintf(
            'Revisión inicial cerrada: %d sesiones cerradas, %d clases dadas por buenas y %d precios confirmados. Queda registrado.',
            $r['sessions'], $r['debts'], $r['prices']
        ));
        return redirect()->to('/finanzas/revision');
    }

    /** Confirma (o corrige con motivo) el precio real pagado de un bono. */
    public function confirmPrice(int $bonoId)
    {
        $res = (new BonoControlService())->confirmPrice(
            $bonoId,
            $this->request->getPost('price'),
            $this->request->getPost('reason'),
            (int) $this->currentUserId()
        );
        if ($res['success']) {
            (new FinanceService())->onBonoPriceChanged($bonoId, (int) $this->currentUserId());
        }
        session()->setFlashdata($res['success'] ? 'success' : 'error', $res['success'] ? 'Precio guardado.' : $res['error']);

        $back = (string) $this->request->getPost('back');
        return redirect()->to($back === 'bono' ? '/bonos/' . $bonoId : '/finanzas/revision#precios');
    }

    // ────────────────────────────────────────────────────────────────

    private function csv(string $filename, array $header, array $rows)
    {
        $fh = fopen('php://temp', 'w+');
        fwrite($fh, "\xEF\xBB\xBF");   // BOM: Excel abre bien los acentos
        fputcsv($fh, $header, ';');
        foreach ($rows as $r) {
            fputcsv($fh, $r, ';');
        }
        rewind($fh);
        $out = stream_get_contents($fh);
        fclose($fh);
        return $this->response
            ->setHeader('Content-Type', 'text/csv; charset=UTF-8')
            ->setHeader('Content-Disposition', 'attachment; filename="' . $filename . '"')
            ->setBody($out);
    }
}
