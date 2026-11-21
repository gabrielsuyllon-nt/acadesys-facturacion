<?php

namespace App\Console\Commands;

use App\Models\Branch;
use App\Models\Company;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;

/**
 * Deja el servicio listo para AcadeSys sin pasos manuales: usuario de la API,
 * empresa de prueba en el ambiente beta de SUNAT, sucursal y certificado.
 * Es idempotente: se ejecuta en cada arranque del contenedor.
 */
class PrepararAcadesys extends Command
{
    protected $signature = 'acadesys:preparar';

    protected $description = 'Configura usuario, empresa beta SUNAT, sucursal y certificado de prueba para AcadeSys';

    public function handle(): int
    {
        $email = trim((string) env('FACT_ADMIN_EMAIL', ''));
        $password = (string) env('FACT_ADMIN_PASSWORD', '');
        if ($email === '' || strlen($password) < 8) {
            $this->warn('FACT_ADMIN_EMAIL y FACT_ADMIN_PASSWORD (mínimo 8 caracteres) son obligatorios; no se configuró nada.');
            return self::SUCCESS;
        }

        // Certificado de prueba incluido en el repositorio (RUC 20161515648).
        $origen = base_path('ejemplos-postman/certificado_prueba/certificado.pem');
        $destino = storage_path('app/public/certificado/certificado.pem');
        if (!File::exists($destino) && File::exists($origen)) {
            File::ensureDirectoryExists(dirname($destino));
            File::copy($origen, $destino);
        }

        if (DB::table('ubi_regiones')->count() === 0) {
            $this->call('db:seed', ['--force' => true]);
        }

        if (Role::count() === 0) {
            (new RolesAndPermissionsSeeder())->runPermissionsAndRolesOnly();
        }
        $rol = Role::where('name', 'super_admin')->firstOrFail();

        $usuario = User::where('email', $email)->first();
        if (!$usuario) {
            $usuario = User::create([
                'name' => env('FACT_ADMIN_NAME', 'AcadeSys'),
                'email' => $email,
                'password' => Hash::make($password),
                'role_id' => $rol->id,
                'user_type' => 'system',
                'active' => true,
                'email_verified_at' => now(),
            ]);
        } elseif (!Hash::check($password, $usuario->password)) {
            $usuario->update(['password' => Hash::make($password)]);
        }

        $empresa = Company::updateOrCreate(
            ['ruc' => env('FACT_EMPRESA_RUC', '20161515648')],
            [
                'razon_social' => env('FACT_EMPRESA_RAZON_SOCIAL', 'ACADEMIA ACADESYS S.A.C.'),
                'nombre_comercial' => env('FACT_EMPRESA_NOMBRE_COMERCIAL', 'AcadeSys'),
                'direccion' => env('FACT_EMPRESA_DIRECCION', 'AV. UNIVERSITARIA 1801'),
                'ubigeo' => env('FACT_EMPRESA_UBIGEO', '150101'),
                'distrito' => env('FACT_EMPRESA_DISTRITO', 'LIMA'),
                'provincia' => env('FACT_EMPRESA_PROVINCIA', 'LIMA'),
                'departamento' => env('FACT_EMPRESA_DEPARTAMENTO', 'LIMA'),
                'usuario_sol' => env('FACT_SUNAT_USUARIO_SOL', 'MODDATOS'),
                'clave_sol' => env('FACT_SUNAT_CLAVE_SOL', 'moddatos'),
                'certificado_pem' => 'certificado/certificado.pem',
                'certificado_password' => env('FACT_CERTIFICADO_CLAVE', '123456'),
                'endpoint_beta' => 'https://e-beta.sunat.gob.pe/ol-ti-itcpfegem-beta/billService',
                'endpoint_produccion' => 'https://e-factura.sunat.gob.pe/ol-ti-itcpfegem/billService',
                'modo_produccion' => false,
                'activo' => true,
            ]
        );

        $sucursal = Branch::updateOrCreate(
            ['company_id' => $empresa->id, 'codigo' => '0000'],
            [
                'nombre' => 'Sucursal Principal',
                'direccion' => $empresa->direccion,
                'ubigeo' => $empresa->ubigeo,
                'distrito' => $empresa->distrito,
                'provincia' => $empresa->provincia,
                'departamento' => $empresa->departamento,
                'activo' => true,
            ]
        );

        $this->info("AcadeSys listo: FACT_API_COMPANY_ID={$empresa->id} FACT_API_BRANCH_ID={$sucursal->id} usuario={$usuario->email}");
        return self::SUCCESS;
    }
}
