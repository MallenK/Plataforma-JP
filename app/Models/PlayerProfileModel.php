<?php

namespace App\Models;

use CodeIgniter\Model;

class PlayerProfileModel extends Model
{
    protected $table = 'player_profiles';
    protected $allowedFields = [
        'player_id',
        'birth_date',
        'height',
        'weight',
        'position',
        'level',
        'medical_notes'
    ];
}

?>