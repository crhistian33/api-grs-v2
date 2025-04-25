<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Faker\Factory as Faker;

class WorkerSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $faker = Faker::create('es_ES');
        // Configuración
        $totalRecords = 200; // Total de registros a generar
        $batchSize = 20;    // Registros por lote
        $adminUserId = 1;    // ID del usuario administrador (created_by)

        $this->command->info("Generando $totalRecords registros de trabajadores...");
        $progressBar = $this->command->getOutput()->createProgressBar($totalRecords);

        $workers = [];

        for ($i = 0; $i < $totalRecords; $i++) {
            $birthDate = $faker->dateTimeBetween('-60 years', '-18 years');

            $workers[] = [
                'name' => $faker->firstName . ' ' . $faker->lastName . ' ' . $faker->lastName,
                'dni' => $this->generateSpanishDNI(),
                'bank_account' => $this->generateBankAccount(),
                'birth_date' => $birthDate->format('Y-m-d'),
                'company_id' => 1,
                'type_worker_id' => 1,
                'created_by' => $adminUserId,
                'created_at' => now(),
            ];

            if ($i % $batchSize === 0 && $i !== 0) {
                DB::table('workers')->insert($workers);
                $workers = [];
                $progressBar->advance($batchSize);
            }
        }

        // Insertar los registros restantes
        if (!empty($workers)) {
            DB::table('workers')->insert($workers);
            $progressBar->advance(count($workers));
        }

        $progressBar->finish();
        $this->command->info("\n¡Registros de trabajadores generados exitosamente!");
    }

    /**
     * Genera un DNI español válido
     */
    private function generateSpanishDNI(): string
    {
        return str_pad(mt_rand(1, 99999999), 8, '0', STR_PAD_LEFT);
    }

    /**
     * Genera un número de cuenta bancaria español válido
     */
    private function generateBankAccount(): string
    {
        $entidad = str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
        $oficina = str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
        $dc = str_pad(mt_rand(1, 99), 2, '0', STR_PAD_LEFT);
        $cuenta = str_pad(mt_rand(1, 9999999999), 10, '0', STR_PAD_LEFT);

        return $entidad . $oficina . $dc . $cuenta;
    }
}
