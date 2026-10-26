<?php

use App\Support\CpfBlindIndex;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Encrypts cpf, rg and phone in place (Laravel `encrypted` cast format) and adds the CPF blind index.
 *
 * Portable without doctrine/dbal: new columns are added, filled from the old ones, the old columns are
 * dropped and the new ones renamed. NOT NULL is restored natively on MySQL (SQLite is only used by tests).
 */
return new class extends Migration
{
    /** @var array<string, int> plain column => original length */
    private const COLUMNS = ['cpf' => 11, 'rg' => 20, 'phone' => 11];

    private const CHUNK = 500;

    public function up(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->dropIndex(['cpf']);
            $table->text('cpf_encrypted')->nullable()->after('cpf');
            $table->char('cpf_hash', 64)->nullable()->after('cpf_encrypted');
            $table->text('rg_encrypted')->nullable()->after('rg');
            $table->text('phone_encrypted')->nullable()->after('phone');
        });

        $this->convertRows(fn (string $cpf, string $rg, string $phone): array => [
            'cpf_encrypted' => Crypt::encryptString($cpf),
            'cpf_hash' => CpfBlindIndex::hash($cpf),
            'rg_encrypted' => Crypt::encryptString($rg),
            'phone_encrypted' => Crypt::encryptString($phone),
        ]);

        $this->replaceColumns('_encrypted', ['cpf', 'rg', 'phone']);

        Schema::table('patients', function (Blueprint $table) {
            $table->index('cpf_hash');
        });

        if (DB::getDriverName() === 'mysql') {
            Schema::table('patients', function (Blueprint $table) {
                $table->text('cpf')->nullable(false)->change();
                $table->char('cpf_hash', 64)->nullable(false)->change();
                $table->text('rg')->nullable(false)->change();
                $table->text('phone')->nullable(false)->change();
            });
        }
    }

    public function down(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            foreach (self::COLUMNS as $column => $length) {
                $table->string("{$column}_plain", $length)->nullable()->after($column);
            }
        });

        $this->convertRows(fn (string $cpf, string $rg, string $phone): array => [
            'cpf_plain' => Crypt::decryptString($cpf),
            'rg_plain' => Crypt::decryptString($rg),
            'phone_plain' => Crypt::decryptString($phone),
        ]);

        Schema::table('patients', function (Blueprint $table) {
            $table->dropIndex(['cpf_hash']);
        });

        $this->replaceColumns('_plain', ['cpf', 'rg', 'phone', 'cpf_hash']);

        Schema::table('patients', function (Blueprint $table) {
            $table->index('cpf');
        });

        if (DB::getDriverName() === 'mysql') {
            Schema::table('patients', function (Blueprint $table) {
                $table->char('cpf', 11)->nullable(false)->change();
                $table->string('rg', 20)->nullable(false)->change();
                $table->string('phone', 11)->nullable(false)->change();
            });
        }
    }

    /**
     * Rewrites every row (soft-deleted included) in one transaction, in id chunks.
     *
     * @param  Closure(string, string, string): array<string, string>  $convert  receives cpf, rg, phone
     */
    private function convertRows(Closure $convert): void
    {
        DB::transaction(function () use ($convert) {
            DB::table('patients')
                ->select(['id', 'cpf', 'rg', 'phone'])
                ->chunkById(self::CHUNK, function ($rows) use ($convert) {
                    /** @var object{id: int, cpf: string, rg: string, phone: string} $row */
                    foreach ($rows as $row) {
                        DB::table('patients')->where('id', $row->id)->update($convert($row->cpf, $row->rg, $row->phone));
                    }
                });
        });
    }

    /**
     * Drops $drop and renames "{column}{$suffix}" back to "{column}" for the three sensitive columns.
     *
     * @param  array<int, string>  $drop
     */
    private function replaceColumns(string $suffix, array $drop): void
    {
        Schema::table('patients', function (Blueprint $table) use ($drop) {
            $table->dropColumn($drop);
        });

        Schema::table('patients', function (Blueprint $table) use ($suffix) {
            foreach (array_keys(self::COLUMNS) as $column) {
                $table->renameColumn("{$column}{$suffix}", $column);
            }
        });
    }
};
