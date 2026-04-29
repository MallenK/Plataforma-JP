<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class AcademySettingsSeeder extends Seeder
{
    public function run()
    {
        $data = [
            ['setting_key' => 'academy_name',        'setting_value' => 'JP Preparation', 'setting_type' => 'string'],
            ['setting_key' => 'academy_email',        'setting_value' => '',               'setting_type' => 'string'],
            ['setting_key' => 'academy_phone',        'setting_value' => '',               'setting_type' => 'string'],
            ['setting_key' => 'academy_language',     'setting_value' => 'es',             'setting_type' => 'string'],
            ['setting_key' => 'academy_timezone',     'setting_value' => 'Europe/Madrid',  'setting_type' => 'string'],
            ['setting_key' => 'academy_currency',     'setting_value' => 'EUR',            'setting_type' => 'string'],
            ['setting_key' => 'academy_location',     'setting_value' => '',               'setting_type' => 'string'],
            ['setting_key' => 'academy_website',      'setting_value' => '',               'setting_type' => 'string'],
            ['setting_key' => 'notif_new_student',    'setting_value' => '1',              'setting_type' => 'bool'],
            ['setting_key' => 'notif_bono_expiry',    'setting_value' => '1',              'setting_type' => 'bool'],
            ['setting_key' => 'notif_class_reminder', 'setting_value' => '1',              'setting_type' => 'bool'],
            ['setting_key' => 'notif_payment_due',    'setting_value' => '1',              'setting_type' => 'bool'],
            ['setting_key' => 'smtp_host',            'setting_value' => '',               'setting_type' => 'string'],
            ['setting_key' => 'smtp_port',            'setting_value' => '587',            'setting_type' => 'int'],
            ['setting_key' => 'smtp_encryption',      'setting_value' => 'tls',            'setting_type' => 'string'],
            ['setting_key' => 'smtp_user',            'setting_value' => '',               'setting_type' => 'string'],
            ['setting_key' => 'smtp_pass',            'setting_value' => '',               'setting_type' => 'string'],
            ['setting_key' => 'smtp_from_name',       'setting_value' => 'JP Preparation', 'setting_type' => 'string'],
            ['setting_key' => 'smtp_from_email',      'setting_value' => '',               'setting_type' => 'string'],
            ['setting_key' => 'sec_min_password',     'setting_value' => '8',              'setting_type' => 'int'],
            ['setting_key' => 'sec_require_upper',    'setting_value' => '0',              'setting_type' => 'bool'],
            ['setting_key' => 'sec_require_numbers',  'setting_value' => '0',              'setting_type' => 'bool'],
            ['setting_key' => 'sec_require_special',  'setting_value' => '0',              'setting_type' => 'bool'],
            ['setting_key' => 'sec_session_timeout',  'setting_value' => '10',             'setting_type' => 'int'],
            ['setting_key' => 'web_active',           'setting_value' => '0',              'setting_type' => 'bool'],
            ['setting_key' => 'web_instagram',        'setting_value' => '',               'setting_type' => 'string'],
            ['setting_key' => 'web_twitter',          'setting_value' => '',               'setting_type' => 'string'],
            ['setting_key' => 'web_facebook',         'setting_value' => '',               'setting_type' => 'string'],
            ['setting_key' => 'web_youtube',          'setting_value' => '',               'setting_type' => 'string'],
            ['setting_key' => 'web_tiktok',           'setting_value' => '',               'setting_type' => 'string'],
        ];

        $this->db->table('academy_settings')->insertBatch($data);
    }
}
