<?php

namespace Database\Seeders;

use App\Models\Patient;
use Illuminate\Database\Seeder;

class PatientSeeder extends Seeder
{
    /**
     * Idempotent: only seeds an empty table, so container restarts don't duplicate data.
     */
    public function run(): void
    {
        if (Patient::withTrashed()->exists()) {
            return;
        }

        Patient::factory()->count(30)->create();
    }
}
