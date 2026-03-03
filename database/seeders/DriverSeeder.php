<?php

namespace Database\Seeders;

use App\Enums\LicenseCategory;
use App\Models\DocumentType;
use App\Models\Driver;
use App\Models\Eps;
use App\Models\PensionFund;
use App\Models\SeveranceFund;
use Illuminate\Database\Seeder;

class DriverSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $cc = DocumentType::where('code', 'CC')->first();
        $nuevaEps = Eps::where('name', 'Nueva EPS')->first();
        $suraEps = Eps::where('name', 'Sura EPS')->first();
        $sanitas = Eps::where('name', 'Sanitas')->first();
        $saludTotal = Eps::where('name', 'Salud Total')->first();
        $coomeva = Eps::where('name', 'Coomeva EPS')->first();
        $porvenir = PensionFund::where('name', 'Porvenir')->first();
        $proteccion = PensionFund::where('name', 'Proteccion')->first();
        $colfondos = PensionFund::where('name', 'Colfondos')->first();
        $oldMutual = PensionFund::where('name', 'Old Mutual')->first();
        $sfPorvenir = SeveranceFund::where('name', 'Porvenir')->first();
        $sfProteccion = SeveranceFund::where('name', 'Proteccion')->first();
        $sfColfondos = SeveranceFund::where('name', 'Colfondos')->first();
        $sfFna = SeveranceFund::where('name', 'FNA')->first();

        $drivers = [
            [
                'document_type_id' => $cc->id,
                'identification_number' => '1020345678',
                'first_name' => 'Carlos',
                'second_name' => 'Andres',
                'first_lastname' => 'Martinez',
                'second_lastname' => 'Lopez',
                'city' => 'Bogota',
                'address' => 'Calle 80 # 25-10',
                'phone' => '3101112233',
                'email' => 'carlos.martinez@correo.com',
                'license_category' => LicenseCategory::C2->value,
                'license_due_date' => '2027-06-15',
                'eps_id' => $nuevaEps->id,
                'pension_fund_id' => $porvenir->id,
                'severance_fund_id' => $sfPorvenir->id,
                'has_social_security' => true,
                'active' => true,
            ],
            [
                'document_type_id' => $cc->id,
                'identification_number' => '79876543',
                'first_name' => 'Jorge',
                'second_name' => 'Eduardo',
                'first_lastname' => 'Ramirez',
                'second_lastname' => 'Torres',
                'city' => 'Bogota',
                'address' => 'Carrera 15 # 60-30',
                'phone' => '3204445566',
                'email' => 'jorge.ramirez@correo.com',
                'license_category' => LicenseCategory::C3->value,
                'license_due_date' => '2028-03-20',
                'eps_id' => $suraEps->id,
                'pension_fund_id' => $proteccion->id,
                'severance_fund_id' => $sfProteccion->id,
                'has_social_security' => true,
                'active' => true,
            ],
            [
                'document_type_id' => $cc->id,
                'identification_number' => '1098765432',
                'first_name' => 'Luis',
                'second_name' => 'Fernando',
                'first_lastname' => 'Hernandez',
                'second_lastname' => 'Diaz',
                'city' => 'Medellin',
                'address' => 'Calle 50 # 40-22',
                'phone' => '3157778899',
                'email' => 'luis.hernandez@correo.com',
                'license_category' => LicenseCategory::C2->value,
                'license_due_date' => '2027-11-10',
                'eps_id' => $sanitas->id,
                'pension_fund_id' => $colfondos->id,
                'severance_fund_id' => $sfFna->id,
                'has_social_security' => true,
                'active' => true,
            ],
            [
                'document_type_id' => $cc->id,
                'identification_number' => '80112233',
                'first_name' => 'Pedro',
                'second_name' => null,
                'first_lastname' => 'Vargas',
                'second_lastname' => 'Castillo',
                'city' => 'Bogota',
                'address' => 'Avenida 68 # 12-45',
                'phone' => '3189990011',
                'email' => 'pedro.vargas@correo.com',
                'license_category' => LicenseCategory::C1->value,
                'license_due_date' => '2026-09-30',
                'eps_id' => $saludTotal->id,
                'pension_fund_id' => $oldMutual->id,
                'severance_fund_id' => $sfColfondos->id,
                'has_social_security' => true,
                'active' => true,
            ],
            [
                'document_type_id' => $cc->id,
                'identification_number' => '1055667788',
                'first_name' => 'Miguel',
                'second_name' => 'Angel',
                'first_lastname' => 'Rojas',
                'second_lastname' => 'Moreno',
                'city' => 'Bucaramanga',
                'address' => 'Calle 45 # 28-17',
                'phone' => '3162223344',
                'email' => 'miguel.rojas@correo.com',
                'license_category' => LicenseCategory::C3->value,
                'license_due_date' => '2028-01-15',
                'eps_id' => $coomeva->id,
                'pension_fund_id' => $porvenir->id,
                'severance_fund_id' => $sfProteccion->id,
                'has_social_security' => true,
                'active' => true,
            ],
        ];

        foreach ($drivers as $driver) {
            Driver::firstOrCreate(
                ['identification_number' => $driver['identification_number']],
                $driver,
            );
        }
    }
}
