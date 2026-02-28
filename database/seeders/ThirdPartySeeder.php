<?php

namespace Database\Seeders;

use App\Models\DocumentType;
use App\Models\ThirdParty;
use Illuminate\Database\Seeder;

class ThirdPartySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $cc = DocumentType::where('code', 'CC')->first();
        $nit = DocumentType::where('code', 'NIT')->first();

        $thirdParties = [
            [
                'document_type_id' => $nit->id,
                'identification_number' => '900123456',
                'is_natural_person' => false,
                'company_name' => 'Clinica San Rafael S.A.S.',
                'trade_name' => 'Clinica San Rafael',
                'city' => 'Bogota',
                'address' => 'Calle 17 # 12-34',
                'phone' => '3101234567',
                'email' => 'contacto@clinicasanrafael.com',
                'is_customer' => true,
                'is_provider' => false,
                'active' => true,
            ],
            [
                'document_type_id' => $nit->id,
                'identification_number' => '800456789',
                'is_natural_person' => false,
                'company_name' => 'Colegio Nuestra Senora del Rosario',
                'trade_name' => 'Colegio del Rosario',
                'city' => 'Bogota',
                'address' => 'Carrera 7 # 45-67',
                'phone' => '3209876543',
                'email' => 'admin@colegiorosario.edu.co',
                'is_customer' => true,
                'is_provider' => false,
                'active' => true,
            ],
            [
                'document_type_id' => $nit->id,
                'identification_number' => '901987654',
                'is_natural_person' => false,
                'company_name' => 'Hotel Dann Carlton Bogota',
                'trade_name' => 'Dann Carlton',
                'city' => 'Bogota',
                'address' => 'Avenida 19 # 120-50',
                'phone' => '3157654321',
                'email' => 'reservas@danncarlton.com',
                'is_customer' => true,
                'is_provider' => false,
                'active' => true,
            ],
            [
                'document_type_id' => $nit->id,
                'identification_number' => '860555111',
                'is_natural_person' => false,
                'company_name' => 'Transportes del Norte S.A.',
                'trade_name' => 'Transnorte',
                'city' => 'Bucaramanga',
                'address' => 'Calle 36 # 15-20',
                'phone' => '3178889999',
                'email' => 'gerencia@transnorte.com',
                'is_customer' => false,
                'is_provider' => true,
                'active' => true,
            ],
            [
                'document_type_id' => $cc->id,
                'identification_number' => '79345678',
                'is_natural_person' => true,
                'first_name' => 'Ricardo',
                'second_name' => 'Andres',
                'first_lastname' => 'Gomez',
                'second_lastname' => 'Pineda',
                'city' => 'Medellin',
                'address' => 'Carrera 50 # 30-15',
                'phone' => '3124567890',
                'email' => 'ricardo.gomez@correo.com',
                'is_customer' => true,
                'is_provider' => true,
                'active' => true,
            ],
        ];

        foreach ($thirdParties as $tp) {
            ThirdParty::firstOrCreate(
                ['identification_number' => $tp['identification_number']],
                $tp,
            );
        }
    }
}
