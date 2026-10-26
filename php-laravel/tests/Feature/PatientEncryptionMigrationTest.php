<?php

namespace Tests\Feature;

use App\Models\Patient;
use App\Support\CpfBlindIndex;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Converts existing plaintext rows in place and back (migrations 2026_10_26_000001/000002).
 */
class PatientEncryptionMigrationTest extends TestCase
{
    public function test_migration_encrypts_existing_rows_and_rolls_back(): void
    {
        $this->assertSame(0, Artisan::call('migrate:fresh'));
        $this->assertSame(0, Artisan::call('migrate:rollback', ['--step' => 2]));
        $this->assertFalse(Schema::hasColumn('patients', 'cpf_hash'));

        $now = now();
        DB::table('patients')->insert([
            ['name' => 'Ana', 'cpf' => '62518953060', 'rg' => '35688019X', 'birth_date' => '1990-01-01', 'sex' => 'F', 'phone' => '11928535718', 'created_at' => $now, 'updated_at' => $now, 'deleted_at' => null],
            ['name' => 'Bia', 'cpf' => '52998224725', 'rg' => '123', 'birth_date' => '1980-01-01', 'sex' => 'F', 'phone' => '1132345678', 'created_at' => $now, 'updated_at' => $now, 'deleted_at' => $now],
        ]);

        $this->assertSame(0, Artisan::call('migrate'));

        $rows = DB::table('patients')->orderBy('id')->get();
        $this->assertStringStartsWith('eyJ', $rows[0]->cpf);
        $this->assertSame(CpfBlindIndex::hash('62518953060'), $rows[0]->cpf_hash);
        $this->assertSame(CpfBlindIndex::hash('52998224725'), $rows[1]->cpf_hash);

        $ana = Patient::where('name', 'Ana')->firstOrFail();
        $this->assertSame(['62518953060', '35688019X', '11928535718'], [$ana->cpf, $ana->rg, $ana->phone]);
        $this->assertSame('1132345678', Patient::onlyTrashed()->firstOrFail()->phone);
        $this->getJson('/api/patients?search=625.189.530-60')->assertJsonCount(1, 'data');

        $this->assertSame(0, Artisan::call('migrate:rollback', ['--step' => 2]));

        $this->assertFalse(Schema::hasColumn('patients', 'cpf_hash'));
        $this->assertSame(
            [['62518953060', '35688019X', '11928535718'], ['52998224725', '123', '1132345678']],
            DB::table('patients')->orderBy('id')->get()->map(fn ($row) => [$row->cpf, $row->rg, $row->phone])->all()
        );
    }
}
