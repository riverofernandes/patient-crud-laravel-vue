<?php

namespace App\Support;

use App\Models\Patient;

/**
 * CSV layout of the patient export (GET /api/patients/export): pt-BR Excel friendly
 * (UTF-8 BOM, ";" separator, CRLF), formatted values and formula-injection guard.
 */
final class PatientCsv
{
    public const BOM = "\xEF\xBB\xBF";

    public const SEPARATOR = ';';

    public const EOL = "\r\n";

    private const HEADER = ['Nome', 'CPF', 'RG', 'Data de nascimento', 'Idade', 'Sexo', 'Celular', 'E-mail', 'Descrição', 'CEP', 'Endereço', 'Cidade', 'UF'];

    private const DELETED_AT_HEADER = 'Excluído em';

    private const SEX_LABELS = ['M' => 'Masculino', 'F' => 'Feminino'];

    /** First characters that make spreadsheets treat a cell as a formula. */
    private const FORMULA_PREFIXES = ['=', '+', '-', '@', "\t", "\r"];

    /**
     * @return array<int, string>
     */
    public static function header(bool $withDeletedAt): array
    {
        return $withDeletedAt ? [...self::HEADER, self::DELETED_AT_HEADER] : self::HEADER;
    }

    /**
     * @return array<int, string>
     */
    public static function row(Patient $patient, bool $withDeletedAt): array
    {
        $row = [
            $patient->name,
            self::formatCpf($patient->cpf),
            $patient->rg,
            $patient->birth_date?->format('d/m/Y') ?? '',
            (string) $patient->ageOn(),
            self::SEX_LABELS[$patient->sex] ?? $patient->sex,
            self::formatPhone($patient->phone),
            $patient->email ?? '',
            $patient->description ?? '',
            self::formatCep($patient->address?->cep),
            $patient->address?->oneLine() ?? '',
            $patient->address?->city ?? '',
            $patient->address?->state ?? '',
        ];

        if ($withDeletedAt) {
            $row[] = $patient->deleted_at?->copy()->setTimezone(config('app.timezone'))->format('d/m/Y H:i') ?? '';
        }

        return $row;
    }

    /**
     * Writes one CSV line with every cell guarded.
     *
     * @param  resource  $handle
     * @param  array<int, string>  $cells
     */
    public static function write($handle, array $cells): void
    {
        fwrite($handle, self::line($cells));
    }

    /**
     * One CSV line (RFC 4180): a cell is quoted only when it contains the separator, a quote or a line
     * break (fputcsv would also quote plain spaces, e.g. "Data de nascimento"); quotes are doubled.
     *
     * @param  array<int, string>  $cells
     */
    public static function line(array $cells): string
    {
        $encoded = array_map(function (string $cell): string {
            $cell = self::guard($cell);

            return strpbrk($cell, self::SEPARATOR."\"\r\n") === false ? $cell : '"'.str_replace('"', '""', $cell).'"';
        }, $cells);

        return implode(self::SEPARATOR, $encoded).self::EOL;
    }

    /**
     * Prefixes a quote to cells that a spreadsheet would evaluate as a formula (CSV injection).
     */
    public static function guard(string $value): string
    {
        return $value !== '' && in_array($value[0], self::FORMULA_PREFIXES, true) ? "'".$value : $value;
    }

    public static function formatCpf(string $cpf): string
    {
        return preg_match('/^(\d{3})(\d{3})(\d{3})(\d{2})$/', $cpf, $m) ? "{$m[1]}.{$m[2]}.{$m[3]}-{$m[4]}" : $cpf;
    }

    /**
     * 00000-000; empty when there is no address.
     */
    public static function formatCep(?string $cep): string
    {
        if ($cep === null) {
            return '';
        }

        return preg_match('/^(\d{5})(\d{3})$/', $cep, $m) ? "{$m[1]}-{$m[2]}" : $cep;
    }

    /**
     * (00) 00000-0000 for mobile numbers (11 digits), (00) 0000-0000 for 10 digits; anything else as stored.
     */
    public static function formatPhone(string $phone): string
    {
        return preg_match('/^(\d{2})(\d{4,5})(\d{4})$/', $phone, $m) ? "({$m[1]}) {$m[2]}-{$m[3]}" : $phone;
    }
}
