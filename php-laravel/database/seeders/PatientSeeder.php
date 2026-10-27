<?php

namespace Database\Seeders;

use App\Models\Patient;
use App\Observers\PatientObserver;
use Illuminate\Database\Seeder;

class PatientSeeder extends Seeder
{
    /**
     * Idempotent: only seeds an empty table, so container restarts don't duplicate data.
     *
     * Demo data is not a user action, so it creates no audit entries (the history starts empty). Auditing is
     * switched off through the observer flag rather than Model::withoutEvents(), which would also skip the
     * `saving` hook that fills the CPF blind index.
     */
    public function run(): void
    {
        if (Patient::withTrashed()->exists()) {
            return;
        }

        PatientObserver::withoutAuditing(fn () => Patient::factory()->count(30)->create());
    }
}
