<?php

namespace Tests\Feature;

use App\Models\Patient;
use App\Support\PatientCsv;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class PatientExportTest extends TestCase
{
    use RefreshDatabase;

    private const HEADER = 'Nome;CPF;RG;Data de nascimento;Idade;Sexo;Celular;E-mail;Descrição;CEP;Endereço;Cidade;UF';

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function export(string $query = ''): TestResponse
    {
        return $this->get('/api/patients/export'.($query === '' ? '' : '?'.$query))->assertOk();
    }

    /**
     * @return array<int, string> CSV lines without the BOM and the trailing CRLF
     */
    private function lines(TestResponse $response): array
    {
        $content = $response->streamedContent();
        $this->assertStringStartsWith(PatientCsv::BOM, $content);
        $this->assertStringEndsWith("\r\n", $content);

        return explode("\r\n", substr($content, strlen(PatientCsv::BOM), -2));
    }

    public function test_headers_bom_separator_and_formatted_values(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-26 23:59:00', 'UTC')); // 20:59 in São Paulo
        Patient::factory()->create([
            'name' => 'Maria da Silva',
            'cpf' => '62518953060',
            'rg' => '35688019X',
            'birth_date' => '1996-07-20',
            'sex' => 'F',
            'phone' => '11928535718',
            'email' => 'maria@example.com',
            'description' => 'Miopia; retorno "anual"',
        ]);
        Patient::factory()->create([
            'name' => 'José Souza',
            'cpf' => '52998224725',
            'rg' => '123456',
            'birth_date' => '1980-01-02',
            'sex' => 'M',
            'phone' => '1132345678',
            'email' => null,
            'description' => "Linha 1\nLinha 2",
        ]);

        $response = $this->export();

        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $response->assertHeader('Content-Disposition', 'attachment; filename="pacientes-20261026-2059.csv"');

        $content = substr($response->streamedContent(), strlen(PatientCsv::BOM));
        $this->assertSame(
            self::HEADER."\r\n"
            .'José Souza;529.982.247-25;123456;02/01/1980;46;Masculino;(11) 3234-5678;;"Linha 1'."\n".'Linha 2";;;;'."\r\n"
            .'Maria da Silva;625.189.530-60;35688019X;20/07/1996;30;Feminino;(11) 92853-5718;maria@example.com;"Miopia; retorno ""anual""";;;;'."\r\n",
            $content
        );
        $this->assertStringStartsWith(PatientCsv::BOM, $response->streamedContent());
    }

    public function test_formula_injection_is_neutralized(): void
    {
        Patient::factory()->create(['name' => '=HYPERLINK("http://x")', 'email' => '@evil.example', 'description' => '+SUM(A1)']);
        Patient::factory()->create(['name' => '-2+3', 'email' => null, 'description' => "\tTab"]);

        $lines = $this->lines($this->export());

        $this->assertStringStartsWith('"\'=HYPERLINK(""http://x"")";', $lines[2]);
        $this->assertStringContainsString(";'@evil.example;'+SUM(A1)", $lines[2]);
        $this->assertStringStartsWith("'-2+3;", $lines[1]);
        $this->assertStringEndsWith(";'\tTab;;;;", $lines[1]);

        $this->assertSame("'=1", PatientCsv::guard('=1'));
        $this->assertSame("'\rx", PatientCsv::guard("\rx"));
        $this->assertSame('Ana', PatientCsv::guard('Ana'));
        $this->assertSame('', PatientCsv::guard(''));
    }

    public function test_trashed_only_adds_the_deleted_at_column(): void
    {
        Patient::factory()->create(['name' => 'Ativo']);
        $deleted = Patient::factory()->create(['name' => 'Removido']);
        Carbon::setTestNow(Carbon::parse('2026-10-27 01:30:00', 'UTC'));
        $deleted->delete();

        $lines = $this->lines($this->export('trashed=only'));

        $this->assertSame(self::HEADER.';Excluído em', $lines[0]);
        $this->assertCount(2, $lines);
        $this->assertStringStartsWith('Removido;', $lines[1]);
        $this->assertStringEndsWith(';26/10/2026 22:30', $lines[1]);

        $active = $this->lines($this->export());
        $this->assertSame(self::HEADER, $active[0]);
        $this->assertCount(2, $active);
        $this->assertStringStartsWith('Ativo;', $active[1]);
    }

    public function test_filters_and_sorting_are_applied_without_pagination(): void
    {
        Patient::factory()->create(['name' => 'Ana', 'sex' => 'F', 'birth_date' => now()->subYears(40)->toDateString()]);
        Patient::factory()->create(['name' => 'Bia', 'sex' => 'F', 'birth_date' => now()->subYears(20)->toDateString()]);
        Patient::factory()->create(['name' => 'Carlos', 'sex' => 'M', 'birth_date' => now()->subYears(40)->toDateString()]);
        Patient::factory()->create(['name' => 'Dani', 'sex' => 'F', 'birth_date' => now()->subYears(41)->toDateString(), 'cpf' => '62518953060']);
        Patient::factory()->count(12)->create(['name' => 'Zeca', 'sex' => 'M']);

        $names = fn (string $query) => array_map(
            fn (string $line) => explode(';', $line)[0],
            array_slice($this->lines($this->export($query)), 1)
        );

        $this->assertSame(['Dani', 'Ana'], $names('sex=F&age_min=30&sort=name&direction=desc'));
        $this->assertSame(['Dani'], $names('search=625.189.530-60'));
        $this->assertSame([], $names('search=625.189'));
        $this->assertCount(16, $names('per_page=2'));
    }

    public function test_empty_export_has_only_the_header(): void
    {
        $this->assertSame([self::HEADER], $this->lines($this->export()));
    }

    public function test_export_route_is_not_shadowed_by_the_patient_route(): void
    {
        $this->get('/api/patients/export')->assertOk()->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $this->getJson('/api/patients/export.json')->assertNotFound();
    }

    public function test_cross_origin_clients_can_read_the_filename(): void
    {
        $this->get('/api/patients/export', ['Origin' => 'http://localhost:5173'])
            ->assertOk()
            ->assertHeader('Access-Control-Allow-Origin', 'http://localhost:5173')
            ->assertHeader('Access-Control-Expose-Headers', 'Content-Disposition, X-Request-Id');
    }

    public function test_formatters(): void
    {
        $this->assertSame('625.189.530-60', PatientCsv::formatCpf('62518953060'));
        $this->assertSame('123', PatientCsv::formatCpf('123'));
        $this->assertSame('(11) 92853-5718', PatientCsv::formatPhone('11928535718'));
        $this->assertSame('(11) 3234-5678', PatientCsv::formatPhone('1132345678'));
        $this->assertSame('123', PatientCsv::formatPhone('123'));
    }
}
