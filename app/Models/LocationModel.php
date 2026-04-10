<?php

namespace App\Models;

use CodeIgniter\Model;

class LocationModel extends Model
{
    protected $table            = 'locations';
    protected $primaryKey       = 'id';
    protected $returnType       = 'array';
    protected $useAutoIncrement = true;
    protected $useTimestamps    = true;
    protected $dateFormat       = 'datetime';
    protected $createdField     = 'created_at';
    protected $updatedField     = 'updated_at';

    protected $allowedFields = [
        'name',
        'description',
        'address',
        'type',
        'capacity',
        'phone',
        'active',
    ];

    protected $validationRules = [
        'name' => 'required|min_length[2]|max_length[150]',
        'type' => 'required|in_list[pitch,gym,room,office,other]',
    ];

    protected $validationMessages = [
        'name' => ['required' => 'El nombre de la sede es obligatorio'],
        'type' => ['required' => 'El tipo de instalación es obligatorio'],
    ];

    public function getActive(): array
    {
        return $this->where('active', 1)->orderBy('name', 'ASC')->findAll();
    }
}
