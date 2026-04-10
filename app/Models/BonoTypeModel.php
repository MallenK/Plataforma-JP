<?php

namespace App\Models;

use CodeIgniter\Model;

class BonoTypeModel extends Model
{
    protected $table            = 'bono_types';
    protected $primaryKey       = 'id';
    protected $returnType       = 'array';
    protected $useAutoIncrement = true;
    protected $useTimestamps    = true;
    protected $dateFormat       = 'datetime';
    protected $createdField     = 'created_at';
    protected $updatedField     = 'updated_at';

    protected $allowedFields = [
        'name',
        'sessions',
        'price',
        'validity_days',
        'active',
    ];

    protected $validationRules = [
        'name'          => 'required|min_length[2]|max_length[100]',
        'sessions'      => 'required|is_natural_no_zero',
        'price'         => 'required|decimal',
        'validity_days' => 'required|is_natural_no_zero',
    ];

    protected $validationMessages = [
        'name'          => ['required' => 'El nombre del bono es obligatorio'],
        'sessions'      => ['required' => 'El número de sesiones es obligatorio'],
        'price'         => ['required' => 'El precio es obligatorio'],
        'validity_days' => ['required' => 'La validez en días es obligatoria'],
    ];

    public function getActive(): array
    {
        return $this->where('active', 1)->orderBy('name', 'ASC')->findAll();
    }
}
