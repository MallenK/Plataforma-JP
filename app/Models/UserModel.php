<?php

namespace App\Models;

use CodeIgniter\Model;

class UserModel extends Model
{
    protected $table            = 'users';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;

    protected $protectFields    = true;
    protected $allowedFields    = [
        'name',
        'email',
        'password',
        'role'
    ];

    protected bool $allowEmptyInserts = false;
    protected bool $updateOnlyChanged = true;

    // Casts (opcional)
    protected array $casts = [];
    protected array $castHandlers = [];

    /*
    |--------------------------------------------------------------------------
    | Dates
    |--------------------------------------------------------------------------
    */
    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = null;
    protected $deletedField  = null;

    /*
    |--------------------------------------------------------------------------
    | Validation
    |--------------------------------------------------------------------------
    */
    protected $validationRules = [
        'name'     => 'required|min_length[3]|max_length[150]',
        'email'    => 'required|valid_email|is_unique[users.email,id,{id}]',
        'password' => 'required|min_length[6]'
    ];

    protected $validationMessages = [
        'name' => [
            'required'   => 'El nombre es obligatorio',
            'min_length' => 'El nombre debe tener mínimo 3 caracteres'
        ],
        'email' => [
            'required'    => 'El email es obligatorio',
            'valid_email' => 'El email no es válido',
            'is_unique'   => 'Este email ya está registrado'
        ],
        'password' => [
            'required'   => 'La contraseña es obligatoria',
            'min_length' => 'La contraseña debe tener mínimo 6 caracteres'
        ]
    ];

    protected $skipValidation       = false;
    protected $cleanValidationRules = true;

    /*
    |--------------------------------------------------------------------------
    | Callbacks
    |--------------------------------------------------------------------------
    */
    protected $allowCallbacks = true;
    protected $beforeInsert   = ['hashPassword'];
    protected $beforeUpdate   = ['hashPassword'];

    protected $afterInsert    = [];
    protected $afterUpdate    = [];
    protected $beforeFind     = [];
    protected $afterFind      = [];
    protected $beforeDelete   = [];
    protected $afterDelete    = [];

    /*
    |--------------------------------------------------------------------------
    | Password Hashing automático
    |--------------------------------------------------------------------------
    */
    protected function hashPassword(array $data)
    {
        if (!isset($data['data']['password'])) {
            return $data;
        }

        $data['data']['password'] = password_hash($data['data']['password'], PASSWORD_BCRYPT);

        return $data;
    }
}