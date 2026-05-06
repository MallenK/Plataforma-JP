<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * PlayerMetricModel — Métricas físicas / técnicas de un alumno.
 *
 * La tabla `player_metrics` está pensada para guardar valores periódicos
 * sobre un alumno: peso, % de grasa, VO2 máx, frecuencia cardíaca,
 * tests técnicos, etc. La columna `metrics` es JSON, así que puedes
 * almacenar cualquier conjunto de pares clave/valor.
 *
 * EJEMPLO DE PAYLOAD `metrics` (modo plantilla — modifícalo libremente):
 * {
 *     "weight_kg":      72.5,
 *     "body_fat_pct":   14.2,
 *     "height_cm":      178,
 *     "resting_hr":     58,
 *     "vo2_max":        56.4,
 *     "vertical_jump":  62,
 *     "sprint_30m_s":   4.21,
 *     "test_technical": "8/10",
 *     "category":       "physical"
 * }
 *
 * El campo `evaluation` se usa para una valoración global tipo nota
 * ("A", "Bien", "8/10", etc.) y `notes` para texto libre del entrenador.
 */
class PlayerMetricModel extends Model
{
    protected $table            = 'player_metrics';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useTimestamps    = false; // la tabla solo tiene created_at

    protected $allowedFields = [
        'player_id',
        'coach_id',
        'session_id',
        'date',
        'metrics',
        'evaluation',
        'notes',
        'created_at',
    ];

    /**
     * Últimas N métricas de un alumno, con datos del entrenador.
     */
    public function getRecentForPlayer(int $playerId, int $limit = 10): array
    {
        return $this->db->table('player_metrics pm')
            ->select('pm.*, u.name AS coach_name, u.avatar AS coach_avatar')
            ->join('users u', 'u.id = pm.coach_id', 'left')
            ->where('pm.player_id', $playerId)
            ->orderBy('pm.date', 'DESC')
            ->orderBy('pm.id', 'DESC')
            ->limit($limit)
            ->get()->getResultArray();
    }
}
