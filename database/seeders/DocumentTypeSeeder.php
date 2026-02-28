<?php

namespace Database\Seeders;

use App\Models\DocumentType;
use Illuminate\Database\Seeder;

class DocumentTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $types = [
            ['code' => 'CC', 'name' => 'Cedula de Ciudadania', 'is_natural_person' => true, 'is_legal_person' => false],
            ['code' => 'NIT', 'name' => 'Numero de Identificacion Tributaria', 'is_natural_person' => false, 'is_legal_person' => true],
            ['code' => 'CE', 'name' => 'Cedula de Extranjeria', 'is_natural_person' => true, 'is_legal_person' => false],
            ['code' => 'TI', 'name' => 'Tarjeta de Identidad', 'is_natural_person' => true, 'is_legal_person' => false],
            ['code' => 'PP', 'name' => 'Pasaporte', 'is_natural_person' => true, 'is_legal_person' => false],
        ];

        foreach ($types as $type) {
            DocumentType::firstOrCreate(
                ['code' => $type['code']],
                $type,
            );
        }
    }
}
