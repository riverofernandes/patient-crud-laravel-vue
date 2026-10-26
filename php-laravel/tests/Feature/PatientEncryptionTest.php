<?php

namespace Tests\Feature;

use App\Models\Patient;
use App\Support\CpfBlindIndex;
use Illuminate\Encryption\MissingAppKeyException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\PendingCommand;
use Tests\TestCase;

class PatientEncryptionTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Maria da Silva',
            'cpf' => '625.189.530-60',
            'rg' => '35.688.019-x',
            'birth_date' => '1996-07-20',
            'sex' => 'F',
            'phone' => '(11) 92853-5718',
        ], $overrides);
    }

    public function test_cpf_rg_and_phone_are_stored_encrypted_and_returned_in_clear(): void
    {
        $id = $this->postJson('/api/patients', $this->payload())->assertCreated()->json('data.id');

        $row = (array) DB::table('patients')->where('id', $id)->first();

        foreach (['cpf' => '62518953060', 'rg' => '35688019X', 'phone' => '11928535718'] as $column => $plain) {
            $stored = (string) $row[$column];
            $this->assertNotSame($plain, $stored);
            $this->assertStringNotContainsString($plain, $stored);
            $this->assertSame($plain, Crypt::decryptString($stored));
        }

        $this->assertSame(CpfBlindIndex::hash('62518953060'), $row['cpf_hash']);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', (string) $row['cpf_hash']);

        $this->getJson("/api/patients/{$id}")
            ->assertOk()
            ->assertJsonPath('data.cpf', '62518953060')
            ->assertJsonPath('data.rg', '35688019X')
            ->assertJsonPath('data.phone', '11928535718')
            ->assertJsonMissingPath('data.cpf_hash');
    }

    public function test_blind_index_ignores_masks_and_is_keyed(): void
    {
        $this->assertSame(CpfBlindIndex::hash('62518953060'), CpfBlindIndex::hash('625.189.530-60'));
        $this->assertNotSame(hash('sha256', '62518953060'), CpfBlindIndex::hash('62518953060'));

        $default = CpfBlindIndex::hash('62518953060');
        config(['patients.cpf_hash_key' => 'dedicated-secret']);
        $this->assertNotSame($default, CpfBlindIndex::hash('62518953060'));
        $this->assertSame(hash_hmac('sha256', '62518953060', 'dedicated-secret'), CpfBlindIndex::hash('62518953060'));
    }

    public function test_blind_index_requires_a_key(): void
    {
        config(['patients.cpf_hash_key' => null, 'app.key' => '']);

        $this->expectException(MissingAppKeyException::class);

        CpfBlindIndex::hash('62518953060');
    }

    public function test_cpf_hash_follows_cpf_changes(): void
    {
        $patient = Patient::factory()->create(['cpf' => '62518953060']);

        $this->putJson("/api/patients/{$patient->id}", $this->payload(['cpf' => '529.982.247-25']))->assertOk();

        $this->assertSame(CpfBlindIndex::hash('52998224725'), $patient->refresh()->cpf_hash);
    }

    public function test_search_matches_only_a_full_cpf(): void
    {
        $patient = Patient::factory()->create(['cpf' => '62518953060', 'name' => 'Carlos Alberto']);
        Patient::factory()->create(['cpf' => '52998224725', 'name' => 'Daniela Prado']);

        foreach (['62518953060', '625.189.530-60', ' 625.189.530-60 ', '625 189 530 60'] as $term) {
            $this->getJson('/api/patients?search='.urlencode($term))
                ->assertJsonCount(1, 'data')
                ->assertJsonPath('data.0.id', $patient->id);
        }

        foreach (['625', '625.189', '6251895306', '625189530600', '89530'] as $partial) {
            $this->getJson('/api/patients?search='.urlencode($partial))->assertJsonCount(0, 'data');
        }
    }

    public function test_uniqueness_uses_the_blind_index(): void
    {
        Patient::factory()->create(['cpf' => '62518953060']);

        $this->postJson('/api/patients', $this->payload())
            ->assertUnprocessable()
            ->assertJsonPath('errors.cpf.0', 'Já existe um paciente cadastrado com este CPF.');

        $this->getJson('/api/patients/cpf-availability?cpf='.urlencode('625.189.530-60'))
            ->assertExactJson(['available' => false, 'message' => 'Já existe um paciente cadastrado com este CPF.']);
        $this->getJson('/api/patients/cpf-availability?cpf=52998224725')->assertExactJson(['available' => true]);
    }

    public function test_restore_conflict_uses_the_blind_index(): void
    {
        $old = Patient::factory()->create(['cpf' => '62518953060']);
        $old->delete();
        Patient::factory()->create(['cpf' => '62518953060']);

        $this->postJson("/api/patients/{$old->id}/restore")
            ->assertStatus(409)
            ->assertExactJson(['message' => 'Já existe um paciente ativo com este CPF.']);
    }

    public function test_rehash_command_recomputes_hashes_after_a_key_change(): void
    {
        $active = Patient::factory()->create(['cpf' => '62518953060']);
        $trashed = Patient::factory()->create(['cpf' => '52998224725']);
        $trashed->delete();
        $updatedAt = $active->refresh()->updated_at;

        config(['patients.cpf_hash_key' => 'rotated-secret']);
        $this->getJson('/api/patients?search=62518953060')->assertJsonCount(0, 'data');

        /** @var PendingCommand $command artisan() returns int only when console output mocking is disabled */
        $command = $this->artisan('patients:rehash-cpf');
        $command->expectsOutput('2 paciente(s) atualizado(s).')->assertSuccessful()->run();

        $this->getJson('/api/patients?search=62518953060')->assertJsonCount(1, 'data');
        $this->assertSame(CpfBlindIndex::hash('52998224725'), Patient::withTrashed()->findOrFail($trashed->id)->cpf_hash);
        $this->assertEquals($updatedAt, $active->refresh()->updated_at);
        $this->assertSame(0, $active->audits()->where('event', 'updated')->count());
    }
}
