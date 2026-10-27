<?php

namespace Tests\Feature;

use App\Models\Patient;
use App\Models\PatientAddress;
use App\Models\PatientAudit;
use App\Support\PatientCsv;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PatientAddressTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function address(array $overrides = []): array
    {
        return array_merge([
            'cep' => '01310-100',
            'street' => 'Avenida Paulista',
            'number' => '1578',
            'complement' => 'Sala 12',
            'neighborhood' => 'Bela Vista',
            'city' => 'São Paulo',
            'state' => 'sp',
        ], $overrides);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Maria da Silva',
            'cpf' => '625.189.530-60',
            'rg' => '35688019X',
            'birth_date' => '1996-07-20',
            'sex' => 'F',
            'phone' => '11928535718',
        ], $overrides);
    }

    private function createWithAddress(): int
    {
        return (int) $this->postJson('/api/patients', $this->payload(['address' => $this->address()]))
            ->assertCreated()
            ->json('data.id');
    }

    public function test_address_is_normalized_returned_and_encrypted_at_rest(): void
    {
        $id = $this->createWithAddress();

        $expected = [
            'cep' => '01310100',
            'street' => 'Avenida Paulista',
            'number' => '1578',
            'complement' => 'Sala 12',
            'neighborhood' => 'Bela Vista',
            'city' => 'São Paulo',
            'state' => 'SP',
        ];

        $this->getJson("/api/patients/{$id}")->assertOk()->assertJsonPath('data.address', $expected);
        $this->getJson('/api/patients')->assertOk()->assertJsonPath('data.0.address', $expected);

        $row = (array) DB::table('patient_addresses')->where('patient_id', $id)->first();

        foreach (['street', 'number', 'complement', 'neighborhood'] as $column) {
            $this->assertStringNotContainsString($expected[$column], (string) $row[$column]);
            $this->assertSame($expected[$column], Crypt::decryptString((string) $row[$column]));
        }

        $this->assertSame('01310100', $row['cep']);
        $this->assertSame('São Paulo', $row['city']);
        $this->assertSame('SP', $row['state']);
    }

    public function test_address_is_optional_and_complement_may_be_omitted(): void
    {
        $this->postJson('/api/patients', $this->payload())
            ->assertCreated()
            ->assertJsonPath('data.address', null)
            ->assertJsonPath('data.photo_url', null);

        $address = $this->address(['number' => 'S/N']);
        unset($address['complement']);

        $this->postJson('/api/patients', $this->payload(['cpf' => '52998224725', 'address' => $address]))
            ->assertCreated()
            ->assertJsonPath('data.address.number', 'S/N')
            ->assertJsonPath('data.address.complement', null);

        $this->postJson('/api/patients', $this->payload(['cpf' => '11144477735', 'address' => null]))
            ->assertCreated()
            ->assertJsonPath('data.address', null);

        $this->assertSame(1, PatientAddress::count());
    }

    public function test_empty_address_object_requires_every_subfield_except_complement(): void
    {
        $response = $this->postJson('/api/patients', $this->payload(['address' => []]))->assertUnprocessable();

        $response->assertJsonValidationErrors([
            'address.cep' => 'O campo CEP é obrigatório.',
            'address.street' => 'O campo logradouro é obrigatório.',
            'address.number' => 'O campo número é obrigatório.',
            'address.neighborhood' => 'O campo bairro é obrigatório.',
            'address.city' => 'O campo cidade é obrigatório.',
            'address.state' => 'O campo UF é obrigatório.',
        ]);
        $response->assertJsonMissingValidationErrors(['address.complement']);
    }

    public function test_partial_and_invalid_addresses_have_pt_br_messages(): void
    {
        $this->postJson('/api/patients', $this->payload(['address' => ['cep' => '01310100']]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['address.street', 'address.number', 'address.neighborhood', 'address.city', 'address.state'])
            ->assertJsonMissingValidationErrors(['address.cep']);

        $response = $this->postJson('/api/patients', $this->payload(['address' => $this->address([
            'cep' => '0131a100',
            'state' => 'XX',
            'street' => str_repeat('a', 256),
            'number' => str_repeat('1', 21),
            'complement' => str_repeat('c', 101),
            'neighborhood' => str_repeat('b', 101),
            'city' => str_repeat('d', 101),
        ])]))->assertUnprocessable();

        $response->assertJsonValidationErrors([
            'address.cep' => 'O campo CEP deve ter 8 dígitos.',
            'address.state' => 'O campo UF deve ser a sigla de um estado brasileiro (ex.: SP).',
            'address.street' => 'O campo logradouro não pode ter mais de 255 caracteres.',
            'address.number' => 'O campo número não pode ter mais de 20 caracteres.',
            'address.complement' => 'O campo complemento não pode ter mais de 100 caracteres.',
            'address.neighborhood' => 'O campo bairro não pode ter mais de 100 caracteres.',
            'address.city' => 'O campo cidade não pode ter mais de 100 caracteres.',
        ]);

        $this->postJson('/api/patients', $this->payload(['address' => $this->address(['cep' => '1234'])]))
            ->assertJsonValidationErrors(['address.cep' => 'O campo CEP deve ter 8 dígitos.']);

        $this->postJson('/api/patients', $this->payload(['address' => $this->address(['street' => 123])]))
            ->assertJsonValidationErrors(['address.street' => 'O campo logradouro deve ser um texto.']);

        $this->postJson('/api/patients', $this->payload(['address' => 'Rua A, 10']))
            ->assertJsonValidationErrors(['address' => 'O campo endereço deve ser um objeto.']);

        foreach (Arr::flatten((array) $response->json('errors')) as $message) {
            $this->assertStringStartsNotWith('The ', (string) $message);
        }
        $this->assertSame(0, Patient::count());
    }

    public function test_put_replaces_and_removes_the_address(): void
    {
        $id = $this->createWithAddress();
        $addressId = PatientAddress::query()->value('id');

        $this->putJson("/api/patients/{$id}", $this->payload(['address' => $this->address(['number' => '2000', 'complement' => null])]))
            ->assertOk()
            ->assertJsonPath('data.address.number', '2000')
            ->assertJsonPath('data.address.complement', null);
        $this->assertSame($addressId, PatientAddress::query()->value('id')); // updated in place

        // PUT is a full replacement: an omitted address is removed...
        $this->putJson("/api/patients/{$id}", $this->payload())->assertOk()->assertJsonPath('data.address', null);
        $this->assertSame(0, PatientAddress::count());

        // ...and so is an explicit null.
        $this->putJson("/api/patients/{$id}", $this->payload(['address' => $this->address()]))->assertOk();
        $this->putJson("/api/patients/{$id}", $this->payload(['address' => null]))->assertOk()->assertJsonPath('data.address', null);
        $this->assertSame(0, PatientAddress::count());
    }

    public function test_created_audit_includes_the_address_fields(): void
    {
        $id = $this->createWithAddress();

        $this->assertSame(1, PatientAudit::query()->where('patient_id', $id)->count());
        $audit = PatientAudit::query()->where('patient_id', $id)->firstOrFail();
        $this->assertSame(PatientAudit::CREATED, $audit->event);
        $this->assertSame(['old' => null, 'new' => '01310100'], $audit->changes['address.cep']);
        $this->assertSame(['old' => null, 'new' => 'Avenida Paulista'], $audit->changes['address.street']);
        $this->assertSame(['old' => null, 'new' => 'SP'], $audit->changes['address.state']);

        // Stored encrypted like every audit payload.
        $raw = (string) DB::table('patient_audits')->where('id', $audit->id)->value('changes');
        $this->assertStringNotContainsString('Avenida Paulista', $raw);
        $this->assertStringContainsString('Avenida Paulista', Crypt::decryptString($raw));
    }

    public function test_address_only_change_writes_exactly_one_audit(): void
    {
        $id = $this->createWithAddress();

        $this->putJson("/api/patients/{$id}", $this->payload(['address' => $this->address(['cep' => '20040-020', 'city' => 'Rio de Janeiro', 'state' => 'RJ'])]))
            ->assertOk();

        $audits = PatientAudit::query()->where('patient_id', $id)->orderBy('id')->get();
        $this->assertCount(2, $audits);
        $latest = $audits->last();
        $this->assertInstanceOf(PatientAudit::class, $latest);
        $this->assertSame(PatientAudit::UPDATED, $latest->event);
        $this->assertSame([
            'address.cep' => ['old' => '01310100', 'new' => '20040020'],
            'address.city' => ['old' => 'São Paulo', 'new' => 'Rio de Janeiro'],
            'address.state' => ['old' => 'SP', 'new' => 'RJ'],
        ], $latest->changes);

        // The same data again: nothing changed, nothing recorded.
        $this->putJson("/api/patients/{$id}", $this->payload(['address' => $this->address(['cep' => '20040020', 'city' => 'Rio de Janeiro', 'state' => 'RJ'])]))
            ->assertOk();
        $this->assertSame(2, PatientAudit::query()->where('patient_id', $id)->count());
    }

    public function test_patient_and_address_changes_share_one_audit(): void
    {
        $id = $this->createWithAddress();

        $this->putJson("/api/patients/{$id}", $this->payload(['name' => 'Maria Lima', 'address' => $this->address(['street' => 'Rua Augusta'])]))
            ->assertOk();

        $audit = PatientAudit::query()->where('patient_id', $id)->latest('id')->firstOrFail();
        $this->assertSame([
            'name' => ['old' => 'Maria da Silva', 'new' => 'Maria Lima'],
            'address.street' => ['old' => 'Avenida Paulista', 'new' => 'Rua Augusta'],
        ], $audit->changes);
        $this->assertSame(2, PatientAudit::query()->where('patient_id', $id)->count());

        // Removing the address records every field going to null.
        $this->putJson("/api/patients/{$id}", $this->payload(['name' => 'Maria Lima']))->assertOk();
        $removed = PatientAudit::query()->where('patient_id', $id)->latest('id')->firstOrFail();
        $this->assertSame(['old' => 'Rua Augusta', 'new' => null], $removed->changes['address.street']);
        $this->assertSame(['old' => 'Sala 12', 'new' => null], $removed->changes['address.complement']);
        $this->assertArrayNotHasKey('name', $removed->changes);
    }

    public function test_csv_export_has_the_address_columns(): void
    {
        $this->createWithAddress();
        $address = $this->address(['street' => '=HYPERLINK("x")', 'complement' => null, 'number' => '10', 'state' => 'MG', 'city' => 'Belo Horizonte']);
        $this->postJson('/api/patients', $this->payload(['name' => 'Ana', 'cpf' => '52998224725', 'address' => $address]))->assertCreated();
        $this->postJson('/api/patients', $this->payload(['name' => 'Zeca', 'cpf' => '11144477735']))->assertCreated();

        $content = $this->get('/api/patients/export')->assertOk()->streamedContent();
        $lines = explode("\r\n", substr($content, strlen(PatientCsv::BOM), -2));

        $this->assertStringEndsWith(';Descrição;CEP;Endereço;Cidade;UF', $lines[0]);
        // Ana: formula guard on the address cell (it starts with the street), no complement part.
        $this->assertStringEndsWith(';01310-100;"\'=HYPERLINK(""x""), 10 - Bela Vista";Belo Horizonte;MG', $lines[1]);
        $this->assertStringEndsWith(';01310-100;Avenida Paulista, 1578 - Sala 12 - Bela Vista;São Paulo;SP', $lines[2]);
        $this->assertStringEndsWith(';;;;', $lines[3]);
    }

    public function test_hard_delete_cascades_the_address(): void
    {
        $id = $this->createWithAddress();

        Patient::withTrashed()->findOrFail($id)->forceDelete();

        $this->assertSame(0, PatientAddress::count());
    }
}
