<?php

namespace App\Models;

use CodeIgniter\Model;

class EventModel extends Model
{
    protected $table            = 'events';
    protected $primaryKey       = 'id';
    protected $returnType       = 'array';
    protected $useAutoIncrement = true;
    protected $useTimestamps    = true;
    protected $createdField     = 'created_at';
    protected $updatedField     = 'updated_at';

    protected $allowedFields = [
        'type', 'name', 'description', 'category',
        'start_date', 'end_date',
        'location', 'concentration_time', 'concentration_place', 'equipment_notes',
        'accommodation_info', 'schedule_info',
        'cancelled', 'created_by',
    ];

    protected $validationRules = [
        'type'       => 'required|in_list[torneo,campus]',
        'name'       => 'required|min_length[3]|max_length[200]',
        'start_date' => 'required|valid_date[Y-m-d]',
        'end_date'   => 'required|valid_date[Y-m-d]',
    ];

    protected $validationMessages = [
        'name'       => ['required' => 'El nombre del evento es obligatorio'],
        'start_date' => ['required' => 'La fecha de inicio es obligatoria'],
        'end_date'   => ['required' => 'La fecha de fin es obligatoria'],
    ];
}
