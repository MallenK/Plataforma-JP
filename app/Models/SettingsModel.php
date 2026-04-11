<?php

namespace App\Models;

use CodeIgniter\Model;

class SettingsModel extends Model
{
    protected $table         = 'academy_settings';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = false;

    protected $allowedFields = [
        'setting_key',
        'setting_value',
        'setting_type',
        'updated_at',
        'updated_by',
    ];

    // ────────────────────────────────────────────────────────────────
    //  Acceso individual
    // ────────────────────────────────────────────────────────────────

    /**
     * Devuelve el valor casteado de un setting, o $default si no existe.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $row = $this->where('setting_key', $key)->first();
        if (!$row) {
            return $default;
        }
        return $this->castValue($row['setting_value'], $row['setting_type']);
    }

    /**
     * Guarda (upsert) un setting individual.
     */
    public function setSetting(string $key, mixed $value, int $updatedBy = 0): void
    {
        $stored = is_array($value) ? json_encode($value) : (string)$value;
        $now    = date('Y-m-d H:i:s');

        $existing = $this->where('setting_key', $key)->first();

        if ($existing) {
            $this->update($existing['id'], [
                'setting_value' => $stored,
                'updated_at'    => $now,
                'updated_by'    => $updatedBy,
            ]);
        } else {
            $this->insert([
                'setting_key'   => $key,
                'setting_value' => $stored,
                'updated_at'    => $now,
                'updated_by'    => $updatedBy,
            ]);
        }
    }

    // ────────────────────────────────────────────────────────────────
    //  Acceso masivo
    // ────────────────────────────────────────────────────────────────

    /**
     * Devuelve todos los settings como array asociativo key => value casteado.
     */
    public function getAll(): array
    {
        $rows   = $this->findAll();
        $result = [];
        foreach ($rows as $row) {
            $result[$row['setting_key']] = $this->castValue(
                $row['setting_value'],
                $row['setting_type']
            );
        }
        return $result;
    }

    /**
     * Guarda múltiples settings de una vez.
     * $data = ['key' => 'value', ...]
     */
    public function setMultiple(array $data, int $updatedBy = 0): void
    {
        foreach ($data as $key => $value) {
            $this->setSetting($key, $value, $updatedBy);
        }
    }

    // ────────────────────────────────────────────────────────────────
    //  Cast interno
    // ────────────────────────────────────────────────────────────────

    private function castValue(mixed $value, string $type): mixed
    {
        return match ($type) {
            'int'  => (int) $value,
            'bool' => (bool)(int) $value,
            'json' => json_decode((string)$value, true) ?? [],
            default => (string) $value,
        };
    }
}
