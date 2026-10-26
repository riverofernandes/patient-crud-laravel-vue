<?php

namespace App\Console\Commands;

use App\Models\Patient;
use App\Support\CpfBlindIndex;
use Illuminate\Console\Command;

/**
 * Recomputes patients.cpf_hash from the decrypted CPFs. Run it after changing the blind index key
 * (CPF_HASH_KEY, or APP_KEY when CPF_HASH_KEY is empty). See config/patients.php.
 */
class RehashPatientCpfs extends Command
{
    protected $signature = 'patients:rehash-cpf';

    protected $description = 'Recalcula o hash (blind index) do CPF de todos os pacientes, inclusive os da lixeira';

    public function handle(): int
    {
        $count = 0;

        Patient::withTrashed()->lazyById(500)->each(function (Patient $patient) use (&$count) {
            // saveQuietly: the hash is derived data, not a change to audit; timestamps stay untouched.
            $patient->forceFill(['cpf_hash' => CpfBlindIndex::hash($patient->cpf)]);
            $patient->timestamps = false;
            $patient->saveQuietly();
            $count++;
        });

        $this->info("{$count} paciente(s) atualizado(s).");

        return self::SUCCESS;
    }
}
