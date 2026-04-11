<?php

namespace App\Models;

use CodeIgniter\Model;

class ClassSessionCoachModel extends Model
{
    protected $table         = 'class_session_coaches';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps  = false;
    protected $allowedFields = ['session_id', 'user_id'];
}
