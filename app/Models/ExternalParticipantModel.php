<?php

namespace App\Models;

use CodeIgniter\Model;

class ExternalParticipantModel extends Model
{
    protected $table            = 'external_participants';
    protected $primaryKey       = 'id';
    protected $returnType       = 'array';
    protected $useAutoIncrement = true;
    protected $useTimestamps    = true;
    protected $createdField     = 'created_at';
    protected $updatedField     = 'updated_at';

    protected $allowedFields = [
        'name', 'type', 'position', 'birth_date', 'phone', 'email', 'notes',
    ];

    protected $validationRules = [
        'name' => 'required|min_length[2]|max_length[150]',
        'type' => 'required|in_list[player,coach,staff]',
    ];

    protected $validationMessages = [
        'name' => ['required' => 'El nombre del participante es obligatorio'],
    ];
}
