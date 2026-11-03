<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    /**
     * Comptes de démarrage. Le mot de passe vient de SEED_PASSWORD (.env) ;
     * en dehors de l'environnement local, seul l'administrateur est créé.
     */
    public function run(): void
    {
        $password = env('SEED_PASSWORD', 'ChangeMoi123!');

        $comptes = [
            ['superadmin@cdphm.mg', 'Super administrateur', 'superadmin'],
            ['admin@cdphm.mg', 'Administrateur', 'admin'],
            ['rindra@cdphm.mg', 'Rindra RH', 'rh'],
        ];

        if (app()->environment('local')) {
            $comptes[] = ['direction@cdphm.mg', 'Direction Générale', 'direction'];
            $comptes[] = ['comptable@cdphm.mg', 'Comptable', 'comptable'];
            $comptes[] = ['raf@cdphm.mg', 'RAF', 'raf'];
            $comptes[] = ['regie@cdphm.mg', 'Régisseuse', 'regisseuse'];
            $comptes[] = ['caisse@cdphm.mg', 'Caissier', 'caissier'];
            $comptes[] = ['cdd@cdphm.mg', 'CDD Quai', 'cdd'];
            $comptes[] = ['magasinier@cdphm.mg', 'Magasinier', 'magasinier'];
            $comptes[] = ['logistique@cdphm.mg', 'Logisticien', 'logisticien'];
            $comptes[] = ['cdc@cdphm.mg', 'CDC Clients', 'cdc'];
        }

        foreach ($comptes as [$email, $name, $role]) {
            $user = User::firstOrNew(['email' => $email]);
            $user->name = $name;
            $user->role_id = Role::where('slug', $role)->value('id');
            $user->actif = true;
            if (! $user->exists) {
                $user->password = $password; // haché par le cast "hashed"
            }
            $user->save();
        }
    }
}
