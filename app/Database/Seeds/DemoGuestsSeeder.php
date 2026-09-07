<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;
use App\Models\UserModel;
use App\Models\PlayerProfileModel;
use App\Models\SettingsModel;

/**
 * Cuentas de INVITADO de la demo + branding neutro.
 *
 * El botón "Entrar como invitado" de /login (visible solo en la demo)
 * inicia sesión con una de estas cuentas sin pedir contraseña
 * (App\Controllers\DemoController::guestLogin).
 *
 * Idempotente: busca por email antes de insertar.
 * Requiere el helper `demo` (autocargado) para demo_guest_accounts().
 */
class DemoGuestsSeeder extends Seeder
{
    public function run()
    {
        helper('demo');

        $userModel    = new UserModel();
        $profileModel = new PlayerProfileModel();
        $password     = demo_guest_password();

        foreach (demo_guest_accounts() as $role => $acc) {
            $user = $userModel->where('email', $acc['email'])->first();

            if (! $user) {
                $uid = $userModel->insert([
                    'name'     => $acc['name'],
                    'email'    => $acc['email'],
                    'password' => $password,
                    'role'     => $role,
                    'status'   => 'active',
                ], true);
                echo "  + invitado {$role}: {$acc['email']}\n";
            } else {
                $uid = (int) $user['id'];
                echo "  = invitado {$role} ya existe\n";
            }

            // El invitado-alumno necesita ficha para que "Mi perfil" no falle.
            if ($role === 'player' && $uid && ! $profileModel->where('player_id', $uid)->first()) {
                $profileModel->insert([
                    'player_id'  => $uid,
                    'birth_date' => '2012-05-14',
                    'height'     => 158,
                    'weight'     => 47,
                    'position'   => PlayerProfileModel::encodePositions(['interior', 'mediapunta']),
                    'category'   => 'infantil',
                    'team'       => 'CF Demo A',
                    'league'     => '1a Territorial',
                ]);
            }
        }

        $this->neutralBranding();
    }

    /**
     * Deja el nombre / remitente de la academia genérico para que la demo
     * no parezca de un cliente concreto.
     */
    private function neutralBranding(): void
    {
        $settings = new SettingsModel();
        $brand = [
            'academy_name'     => 'Tu Plataforma',
            'academy_email'    => 'hola@tuplataforma.example',
            'academy_phone'    => '600 000 000',
            'academy_location' => 'Tu ciudad',
            'academy_website'  => '',
            'smtp_from_name'   => 'Tu Plataforma',
        ];
        foreach ($brand as $k => $v) {
            $settings->setSetting($k, $v);
        }
        echo "  branding neutro aplicado (academy_name = 'Tu Plataforma')\n";
    }
}
