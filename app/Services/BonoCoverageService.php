<?php

namespace App\Services;

use App\Models\SettingsModel;

/**
 * TICKET-013 — Cobertura de bono de las clases (clases ↔ bonos).
 *
 * "Cobertura" = proyección de si el saldo de bono de un alumno alcanza para
 * las clases que tiene programadas. NO descuenta nada: el descuento real
 * sigue ocurriendo al pasar lista (ClasesService::deductBonoForPlayer). Sirve
 * para avisar ANTES de crear/continuar una serie y para marcar cada plaza.
 *
 * Estados de una plaza (alumno en una sesión):
 *   covered   → hay una sesión de bono libre y vigente a esa fecha
 *   at_risk   → hay saldo, pero el bono caduca antes de esa fecha (ampliable)
 *   uncovered → sin saldo: "pendiente de bono" (si luego asiste, se abre deuda)
 *
 * La parte de reparto (allocate) es pura y no toca la BD.
 */
class BonoCoverageService
{
    public const COVERED   = 'covered';
    public const AT_RISK   = 'at_risk';
    public const UNCOVERED = 'uncovered';

    /** Días de antelación para avisar de que un bono caduca pronto. */
    public const EXPIRY_WARN_DAYS = 7;

    private \CodeIgniter\Database\BaseConnection $db;

    public function __construct(?\CodeIgniter\Database\BaseConnection $db = null)
    {
        $this->db = $db ?? \Config\Database::connect();
    }

    // ────────────────────────────────────────────────────────────────
    //  Núcleo puro
    // ────────────────────────────────────────────────────────────────

    /**
     * Reparte el saldo de un alumno entre sus sesiones por orden de fecha.
     *
     * @param string[] $newDates      fechas Y-m-d de las sesiones NUEVAS a evaluar
     * @param string[] $existingDates fechas Y-m-d de sesiones ya programadas (ocupan saldo)
     * @param array    $bonos         [['id'=>int,'remaining'=>int,'expires_at'=>?string], …] en orden FIFO,
     *                                ya filtrados por vigentes a día de hoy
     * @param int      $debtCount     deudas abiertas: consumen saldo antes que cualquier sesión futura
     * @return array<string,array{status:string,bono_id:?int,expires_at:?string}> indexado por fecha nueva
     */
    public static function allocate(array $newDates, array $existingDates, array $bonos, int $debtCount = 0): array
    {
        $pool = [];
        foreach ($bonos as $b) {
            $pool[] = [
                'id'        => (int) $b['id'],
                'remaining' => max(0, (int) $b['remaining']),
                'expires'   => !empty($b['expires_at']) ? (string) $b['expires_at'] : null,
            ];
        }

        // Las deudas saldan primero (FIFO).
        for ($i = 0; $i < $debtCount; $i++) {
            foreach ($pool as &$p) {
                if ($p['remaining'] > 0) {
                    $p['remaining']--;
                    break;
                }
            }
            unset($p);
        }

        // Cada sesión ya programada ocupa una plaza de saldo (aunque repita fecha).
        $events = [];
        foreach ($existingDates as $d) {
            $events[] = ['date' => $d, 'new' => false, 'order' => 0];
        }
        foreach ($newDates as $d) {
            $events[] = ['date' => $d, 'new' => true, 'order' => 1];
        }
        usort($events, fn($a, $b) => [$a['date'], $a['order']] <=> [$b['date'], $b['order']]);

        $result = [];
        foreach ($events as $ev) {
            $date = $ev['date'];

            // Preferir un bono vigente a esa fecha; si no, cualquiera con saldo (en riesgo).
            $pick = null;
            $risk = false;
            foreach ($pool as $i => $p) {
                if ($p['remaining'] > 0 && ($p['expires'] === null || $p['expires'] >= $date)) {
                    $pick = $i;
                    break;
                }
            }
            if ($pick === null) {
                foreach ($pool as $i => $p) {
                    if ($p['remaining'] > 0) {
                        $pick = $i;
                        $risk = true;
                        break;
                    }
                }
            }

            if ($pick !== null) {
                $pool[$pick]['remaining']--;
            }

            if ($ev['new']) {
                $result[$date] = $pick === null
                    ? ['status' => self::UNCOVERED, 'bono_id' => null, 'expires_at' => null]
                    : [
                        'status'     => $risk ? self::AT_RISK : self::COVERED,
                        'bono_id'    => $pool[$pick]['id'],
                        'expires_at' => $pool[$pick]['expires'],
                    ];
            }
        }

        return $result;
    }

    /**
     * Resume los estados de una proyección.
     *
     * @return array{requested:int,covered:int,at_risk:int,uncovered:int,first_uncovered:?string}
     */
    public static function summarize(array $allocation): array
    {
        $s = ['requested' => count($allocation), 'covered' => 0, 'at_risk' => 0, 'uncovered' => 0, 'first_uncovered' => null];
        foreach ($allocation as $date => $a) {
            if ($a['status'] === self::COVERED) {
                $s['covered']++;
            } elseif ($a['status'] === self::AT_RISK) {
                $s['at_risk']++;
            } else {
                $s['uncovered']++;
                $s['first_uncovered'] ??= $date;
            }
        }
        return $s;
    }

    /**
     * Forma JSON-friendly de analyze() para las pantallas (vista previa).
     *
     * @return array<int,array>
     */
    public static function payload(array $coverage): array
    {
        $out = [];
        foreach ($coverage as $c) {
            $dates = [];
            foreach ($c['allocation'] as $date => $a) {
                $dates[$date] = $a['status'];
            }
            $expiring = null;
            foreach ($c['bonos'] as $b) {
                if (!empty($b['expires_at'])
                    && strtotime($b['expires_at']) <= strtotime('+' . self::EXPIRY_WARN_DAYS . ' days')) {
                    $expiring = $b['expires_at'];
                    break;
                }
            }
            $out[] = [
                'player_id'      => $c['player_id'],
                'name'           => $c['name'],
                'never_had_bono' => $c['never_had_bono'],
                'debt_count'     => $c['debt_count'],
                'bonos'          => $c['bonos'],
                'expiring_soon'  => $expiring,
                'summary'        => $c['summary'],
                'dates'          => $dates,
            ];
        }
        return $out;
    }

    /**
     * Fechas de una serie recurrente (Y-m-d) entre dos fechas, para los días
     * ISO indicados (1=Lun … 7=Dom). Única fuente de verdad para crear y previsualizar.
     *
     * @param int[] $days
     * @return string[]
     */
    public static function recurrenceDates(array $days, string $start, string $end): array
    {
        $days = array_map('intval', $days);
        $out  = [];
        try {
            $cur = new \DateTime($start);
            $to  = new \DateTime($end);
        } catch (\Throwable $e) {
            return [];
        }
        while ($cur <= $to) {
            if (in_array((int) $cur->format('N'), $days, true)) {
                $out[] = $cur->format('Y-m-d');
            }
            $cur->modify('+1 day');
        }
        return $out;
    }

    // ────────────────────────────────────────────────────────────────
    //  Con base de datos
    // ────────────────────────────────────────────────────────────────

    /** Fecha del punto de control (Y-m-d) o null si aún no se fijó. */
    public function controlSince(): ?string
    {
        $v = (new SettingsModel())->get('bono_control_since');
        return $v ? (string) $v : null;
    }

    /**
     * Proyecta la cobertura de unos alumnos para unas fechas nuevas.
     *
     * @param int[]    $playerIds
     * @param string[] $dates             fechas Y-m-d nuevas
     * @param int[]    $excludeSessionIds sesiones a ignorar entre las ya programadas (al editar)
     * @return array<int,array> por alumno: name, bonos, never_had_bono, debt_count, allocation, summary
     */
    public function analyze(array $playerIds, array $dates, array $excludeSessionIds = [], ?string $today = null): array
    {
        $playerIds = array_values(array_unique(array_filter(array_map('intval', $playerIds))));
        if (empty($playerIds)) {
            return [];
        }
        $today = $today ?: date('Y-m-d');
        $dates = array_values(array_unique($dates));
        sort($dates);

        $names = [];
        foreach ($this->db->table('users')->select('id, name')->whereIn('id', $playerIds)->get()->getResultArray() as $u) {
            $names[(int) $u['id']] = $u['name'];
        }

        $everHad = [];
        foreach ($this->db->table('player_bonos')->select('player_id, COUNT(*) AS n')
            ->whereIn('player_id', $playerIds)->groupBy('player_id')->get()->getResultArray() as $r) {
            $everHad[(int) $r['player_id']] = (int) $r['n'];
        }

        $bonosBy = [];
        $rows = $this->db->table('player_bonos pb')
            ->select('pb.id, pb.player_id, pb.sessions_remaining, pb.expires_at, bt.name AS bono_name')
            ->join('bono_types bt', 'bt.id = pb.bono_type_id', 'left')
            ->whereIn('pb.player_id', $playerIds)
            ->where('pb.sessions_remaining >', 0)
            ->groupStart()->where('pb.expires_at IS NULL')->orWhere('pb.expires_at >=', $today)->groupEnd()
            ->orderBy('pb.created_at', 'ASC')->orderBy('pb.id', 'ASC')
            ->get()->getResultArray();
        foreach ($rows as $r) {
            $bonosBy[(int) $r['player_id']][] = [
                'id'         => (int) $r['id'],
                'name'       => $r['bono_name'],
                'remaining'  => (int) $r['sessions_remaining'],
                'expires_at' => $r['expires_at'],
            ];
        }

        $existingBy = [];
        $q = $this->db->table('class_session_players csp')
            ->select('csp.user_id, cs.session_date')
            ->join('class_sessions cs', 'cs.id = csp.session_id')
            ->whereIn('csp.user_id', $playerIds)
            ->where('cs.status', 'scheduled')
            ->where('cs.session_date >=', $today)
            ->where('csp.bono_deducted_at IS NULL', null, false);
        if (!empty($excludeSessionIds)) {
            $q->whereNotIn('cs.id', array_map('intval', $excludeSessionIds));
        }
        foreach ($q->get()->getResultArray() as $r) {
            $existingBy[(int) $r['user_id']][] = $r['session_date'];
        }

        $debts = $this->debtCounts($playerIds);

        $out = [];
        foreach ($playerIds as $pid) {
            $alloc = self::allocate($dates, $existingBy[$pid] ?? [], $bonosBy[$pid] ?? [], $debts[$pid] ?? 0);
            $out[$pid] = [
                'player_id'      => $pid,
                'name'           => $names[$pid] ?? ('#' . $pid),
                'bonos'          => $bonosBy[$pid] ?? [],
                'never_had_bono' => empty($everHad[$pid]),
                'debt_count'     => $debts[$pid] ?? 0,
                'allocation'     => $alloc,
                'summary'        => self::summarize($alloc),
            ];
        }
        return $out;
    }

    /**
     * Recalcula la marca `bono_coverage` de las clases FUTURAS ya programadas de
     * un alumno (sin descuento): se llama cuando cambia su saldo (bono nuevo,
     * ampliación, descuento, devolución…). Solo toca la marca; nunca crea ni
     * borra nada. Si dos sesiones caen el mismo día comparten marca.
     */
    public function refreshMarks(int $playerId, ?string $today = null): int
    {
        try {
            $today = $today ?: date('Y-m-d');
            $rows  = $this->db->table('class_session_players csp')
                ->select('csp.id, cs.session_date')
                ->join('class_sessions cs', 'cs.id = csp.session_id')
                ->where('csp.user_id', $playerId)
                ->where('cs.status', 'scheduled')
                ->where('cs.session_date >=', $today)
                ->where('csp.bono_deducted_at IS NULL', null, false)
                ->get()->getResultArray();
            if (empty($rows)) {
                return 0;
            }

            $dates = array_values(array_unique(array_column($rows, 'session_date')));
            sort($dates);

            $bonos = [];
            foreach ($this->db->table('player_bonos')
                ->select('id, sessions_remaining, expires_at')
                ->where('player_id', $playerId)->where('sessions_remaining >', 0)
                ->groupStart()->where('expires_at IS NULL')->orWhere('expires_at >=', $today)->groupEnd()
                ->orderBy('created_at', 'ASC')->orderBy('id', 'ASC')->get()->getResultArray() as $b) {
                $bonos[] = ['id' => (int) $b['id'], 'remaining' => (int) $b['sessions_remaining'], 'expires_at' => $b['expires_at']];
            }

            $alloc = self::allocate($dates, [], $bonos, $this->debtCounts([$playerId])[$playerId] ?? 0);

            $n = 0;
            foreach ($rows as $r) {
                $status = $alloc[$r['session_date']]['status'] ?? self::UNCOVERED;
                $this->db->table('class_session_players')->where('id', $r['id'])->update(['bono_coverage' => $status]);
                $n++;
            }
            return $n;
        } catch (\Throwable $e) {
            log_message('error', 'BonoCoverageService::refreshMarks falló: ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * Deudas abiertas por alumno: sesiones ya dadas (cerradas, con asistencia
     * que consume bono) SIN descuento y sin resolver, posteriores al punto de
     * control. Las anteriores no son deuda: se informan aparte como "no reflejadas".
     *
     * @param int[] $playerIds
     * @return array<int,int>
     */
    public function debtCounts(array $playerIds): array
    {
        $playerIds = array_values(array_filter(array_map('intval', $playerIds)));
        $since     = $this->controlSince();
        if (empty($playerIds) || !$since) {
            return [];
        }
        $rows = $this->db->table('class_session_players csp')
            ->select('csp.user_id, COUNT(*) AS n')
            ->join('class_sessions cs', 'cs.id = csp.session_id')
            ->whereIn('csp.user_id', $playerIds)
            ->where('cs.status', 'completed')
            ->where('cs.session_date >=', $since)
            ->whereIn('csp.attendance', ClasesService::BONO_CONSUMING_ATTENDANCE)
            ->where('csp.bono_deducted_at IS NULL', null, false)
            ->where('csp.bono_resolution IS NULL', null, false)
            ->groupBy('csp.user_id')
            ->get()->getResultArray();
        $out = [];
        foreach ($rows as $r) {
            $out[(int) $r['user_id']] = (int) $r['n'];
        }
        return $out;
    }
}
