<?php

namespace App\Models;

use CodeIgniter\Model;

/** Ficha ligera de una persona externa (sin cuenta) que recibe clases. */
class ClassGuestModel extends Model
{
    protected $table            = 'class_guests';
    protected $primaryKey       = 'id';
    protected $returnType       = 'array';
    protected $useAutoIncrement = true;
    protected $useTimestamps    = true;
    protected $dateFormat       = 'datetime';
    protected $createdField     = 'created_at';
    protected $updatedField     = 'updated_at';

    protected $allowedFields = [
        'first_name', 'last_name', 'phone', 'phone_key', 'email', 'birth_date',
        'guardian_name', 'guardian_phone', 'image_rights', 'notes',
        'converted_user_id', 'created_by', 'archived_at',
    ];
}
