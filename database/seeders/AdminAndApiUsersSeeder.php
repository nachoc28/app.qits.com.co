<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

class AdminAndApiUsersSeeder extends Seeder
{
    public function run(): void
    {
        // Obtener IDs de tipos de usuario
        $idAdmin = DB::table('tipos_usuarios')->where('nombre', 'Administrador')->value('id');
        $idApi   = DB::table('tipos_usuarios')->where('nombre', 'API User')->value('id');

        if (!$idAdmin || !$idApi) {
            $this->command?->error('Faltan tipos_usuarios (Administrador / API User). Ejecuta TiposUsuariosSeeder primero.');
            return;
        }

        // ====== ADMIN ======
        $adminName  = env('ADMIN_NAME',  'Administrador');
        $adminEmail = env('ADMIN_EMAIL', 'admin@example.com');
        $adminPass  = env('ADMIN_PASSWORD'); // si no viene, generamos uno seguro

        if (!$adminPass) {
            $adminPass = Str::random(12);
            $this->command?->warn("ADMIN_PASSWORD no definido en .env, generado temporalmente: {$adminPass}");
        }

        /** @var \App\Models\User $admin */
        $admin = User::updateOrCreate(
            ['email' => $adminEmail],
            [
                'name'              => $adminName,
                'password'          => Hash::make($adminPass),
                'telefono'          => env('ADMIN_PHONE', null),
                'empresa_id'        => env('ADMIN_EMPRESA_ID', null), // o null si no aplica
                'tipo_usuario_id'   => $idAdmin,
                'email_verified_at' => now(),
            ]
        );

        $this->command?->info("Usuario ADMIN listo: {$admin->email}");

        // ====== API USER ======
        $apiName   = env('API_NAME',  'API User');
        $apiEmail  = env('API_EMAIL', 'api@example.com');
        $apiPass   = env('API_PASSWORD'); // si no viene, generamos uno
        if (!$apiPass) {
            $apiPass = Str::random(24);
            $this->command?->warn("API_PASSWORD no definido en .env, generado temporalmente: {$apiPass}");
        }

        /** @var \App\Models\User $apiUser */
        $apiUser = User::updateOrCreate(
            ['email' => $apiEmail],
            [
                'name'              => $apiName,
                'password'          => Hash::make($apiPass),
                'telefono'          => env('API_PHONE', null),
                'empresa_id'        => env('API_EMPRESA_ID', null),
                'tipo_usuario_id'   => $idApi,
                'email_verified_at' => now(),
            ]
        );

        // Opcional: limpiar tokens anteriores y emitir uno nuevo
        if (env('API_REVOKE_OLD_TOKENS', true)) {
            $apiUser->tokens()->delete();
        }
        $token = $apiUser->createToken(env('API_TOKEN_NAME', 'qits-api'), ['utm:write', 'leads:write'])->plainTextToken;

        $this->command?->info("Usuario API listo: {$apiUser->email}");
        $this->command?->warn('Guarda estas credenciales en un gestor seguro.');
        $this->command?->line("API Token (Sanctum):");
        $this->command?->line($token);
    }
}
