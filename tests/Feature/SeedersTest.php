<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SeedersTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        unset($_ENV['SEED_PASSWORD'], $_SERVER['SEED_PASSWORD']);
        parent::tearDown();
    }

    /** .env.example contient « SEED_PASSWORD= » : une valeur vide ne doit jamais devenir le mot de passe. */
    public function test_un_seed_password_vide_retombe_sur_le_mot_de_passe_par_defaut(): void
    {
        $_ENV['SEED_PASSWORD'] = $_SERVER['SEED_PASSWORD'] = '';
        $this->seed(RolePermissionSeeder::class);
        $this->seed(UserSeeder::class);

        $admin = User::where('email', 'admin@cdphm.mg')->firstOrFail();
        $this->assertTrue(Hash::check('ChangeMoi123!', $admin->password));
        $this->assertFalse(Hash::check('', $admin->password));
    }

    public function test_seed_password_defini_est_utilise(): void
    {
        $_ENV['SEED_PASSWORD'] = $_SERVER['SEED_PASSWORD'] = 'Autre-Mot-De-Passe-9';
        $this->seed(RolePermissionSeeder::class);
        $this->seed(UserSeeder::class);

        $this->assertTrue(Hash::check('Autre-Mot-De-Passe-9', User::where('email', 'admin@cdphm.mg')->firstOrFail()->password));
    }
}
