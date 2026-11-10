<?php

namespace App\Models;

use CodeIgniter\Model;

/** Participación de un externo en una clase concreta (tipo + asistencia). */
class ClassSessionGuestModel extends Model
{
    protected $table            = 'class_session_guests';
    protected $primaryKey       = 'id';
    protected $returnType       = 'array';
    protected $useAutoIncrement = true;
    protected $useTimestamps    = true;
    protected $dateFormat       = 'datetime';
    protected $createdField     = 'created_at';
    protected $updatedField     = 'updated_at';

    protected $allowedFields = [
        'session_id', 'guest_id', 'kind', 'attendance', 'coach_id', 'notes', 'created_by', 'price_cents',
    ];
}
