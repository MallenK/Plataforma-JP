<?php

namespace App\Services;

/**
 * Finanzas 2.0 — gastos de la academia (material, reparaciones, alquiler de
 * campos, entrenadores…), asignables a un entrenador o a una sede, con ticket
 * o factura adjunta. Nada se borra: se anulan con motivo.
 *
 * Los adjuntos van FUERA del webroot (writable/uploads/gastos) y solo se sirven
 * por FinanzasController::expenseAttachment (mismo esquema que tickets/mensajes).
 */
class ExpenseService
{
    public const ALLOWED_EXTENSIONS = ['pdf', 'jpg', 'jpeg', 'png', 'webp', 'heic'];
    public const MAX_ATTACHMENT_BYTES = 10 * 1024 * 1024;

    private \CodeIgniter\Database\BaseConnection $db;

    public function __construct(?\CodeIgniter\Database\BaseConnection $db = null)
    {
        $this->db = $db ?? \Config\Database::connect();
        helper('upload');
    }

    /**
     * Valida los datos de un gasto. Pura.
     *
     * @return array{ok:bool,data?:array,error?:string}
     */
    public static function validate(array $in, ?string $today = null): array
    {
        $today  = $today ?: date('Y-m-d');
        $amount = RevisionService::parseEuroToCents($in['amount'] ?? null);
        if ($amount === null || $amount <= 0) {
            return ['ok' => false, 'error' => 'Indica un importe válido mayor que 0.'];
        }
        $date = trim((string) ($in['spent_at'] ?? ''));
        if ($date === '' || strtotime($date) === false) {
            return ['ok' => false, 'error' => 'Indica la fecha del gasto.'];
        }
        $date = date('Y-m-d', strtotime($date));
        if ($date > $today) {
            return ['ok' => false, 'error' => 'La fecha del gasto no puede ser futura.'];
        }
        if (empty($in['category_id'])) {
            return ['ok' => false, 'error' => 'Elige una categoría.'];
        }
        $clean = static fn($v, int $n) => ($v = trim((string) $v)) === '' ? null : mb_substr($v, 0, $n);
        return ['ok' => true, 'data' => [
            'category_id' => (int) $in['category_id'],
            'amount_cents'=> $amount,
            'spent_at'    => $date,
            'method_id'   => !empty($in['method_id']) ? (int) $in['method_id'] : null,
            'supplier'    => $clean($in['supplier'] ?? '', 120),
            'description' => $clean($in['description'] ?? '', 255),
            'staff_id'    => !empty($in['staff_id']) ? (int) $in['staff_id'] : null,
            'location_id' => !empty($in['location_id']) ? (int) $in['location_id'] : null,
        ]];
    }

    /**
     * @param \CodeIgniter\HTTP\Files\UploadedFile|null $file ticket o factura (opcional)
     * @return array{success:bool,id?:int,error?:string}
     */
    public function create(array $in, $file, int $actorId): array
    {
        $v = self::validate($in);
        if (!$v['ok']) {
            return ['success' => false, 'error' => $v['error']];
        }
        $row = $v['data'];

        if ($file && $file->getError() !== UPLOAD_ERR_NO_FILE) {
            if (!$file->isValid() || $file->hasMoved()) {
                return ['success' => false, 'error' => 'No se pudo subir el adjunto.'];
            }
            if ($file->getSize() > self::MAX_ATTACHMENT_BYTES) {
                return ['success' => false, 'error' => 'El adjunto supera los 10 MB.'];
            }
            $ext = upload_allowed_extension($file->getClientExtension(), self::ALLOWED_EXTENSIONS);
            if ($ext === null) {
                return ['success' => false, 'error' => 'Adjunto no permitido (PDF o imagen).'];
            }
            $dir = upload_private_dir('gastos');
            upload_harden_dir($dir);
            $name = bin2hex(random_bytes(16)) . '.' . $ext;
            $file->move($dir, $name);
            $row['attachment_path'] = upload_stored_path('gastos', $name);
            $row['attachment_name'] = mb_substr($file->getClientName(), 0, 255);
        }

        $row['created_by'] = $actorId;
        $row['created_at'] = date('Y-m-d H:i:s');
        $this->db->table('fin_expenses')->insert($row);
        $id = (int) $this->db->insertID();
        AuditService::record('fin_expense', $id, AuditService::CREATE, null, $row, null, $actorId);
        return ['success' => true, 'id' => $id];
    }

    public function void(int $id, ?string $reason, int $actorId): array
    {
        if (!BonoControlService::validReason($reason)) {
            return ['success' => false, 'error' => 'Indica el motivo de la anulación.'];
        }
        $e = $this->db->table('fin_expenses')->where('id', $id)->get()->getRowArray();
        if (!$e || $e['voided_at']) {
            return ['success' => false, 'error' => 'Gasto no encontrado o ya anulado.'];
        }
        $data = ['voided_at' => date('Y-m-d H:i:s'), 'voided_by' => $actorId, 'void_reason' => mb_substr(trim($reason), 0, 255)];
        $this->db->table('fin_expenses')->where('id', $id)->update($data);
        AuditService::record('fin_expense', $id, AuditService::VOID, ['voided_at' => null], $data, $reason, $actorId);
        return ['success' => true];
    }

    /** Gastos de un periodo (incluye anulados, marcados). */
    public function list(string $from, string $to, ?int $categoryId = null, ?int $staffId = null): array
    {
        $q = $this->db->table('fin_expenses e')
            ->select('e.*, c.name AS category_name, m.name AS method_name, u.name AS staff_name, l.name AS location_name, cb.name AS created_by_name')
            ->join('fin_categories c', 'c.id = e.category_id', 'left')
            ->join('fin_payment_methods m', 'm.id = e.method_id', 'left')
            ->join('users u', 'u.id = e.staff_id', 'left')
            ->join('locations l', 'l.id = e.location_id', 'left')
            ->join('users cb', 'cb.id = e.created_by', 'left')
            ->where('e.spent_at >=', $from)->where('e.spent_at <=', $to);
        if ($categoryId) {
            $q->where('e.category_id', $categoryId);
        }
        if ($staffId) {
            $q->where('e.staff_id', $staffId);
        }
        return $q->orderBy('e.spent_at', 'DESC')->orderBy('e.id', 'DESC')->get()->getResultArray();
    }

    public function find(int $id): ?array
    {
        return $this->db->table('fin_expenses')->where('id', $id)->get()->getRowArray() ?: null;
    }
}
