<?php

namespace App\Console\Commands;

use App\Models\Patient;
use App\Support\CpfBlindIndex;
use App\Support\EncryptedFiles;
use Illuminate\Console\Command;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Recomputes patients.cpf_hash from the decrypted CPFs. Run it after changing the blind index key
 * (CPF_HASH_KEY, or APP_KEY when CPF_HASH_KEY is empty). See config/patients.php.
 *
 * Every successful run stores the key's fingerprint (a hash of the key, not the key) on the private disk, which is
 * a volume in production. With --if-key-changed (run by the container entrypoint at start-up) it only rehashes when
 * the stored fingerprint is missing or differs, so "change the key, restart the api" is enough.
 */
class RehashPatientCpfs extends Command
{
    public const FINGERPRINT_FILE = 'cpf-hash-key.fingerprint';

    protected $signature = 'patients:rehash-cpf
        {--if-key-changed : Só recalcula se a chave do hash mudou desde a última execução}';

    protected $description = 'Recalcula o hash (blind index) do CPF de todos os pacientes, inclusive os da lixeira';

    public function handle(): int
    {
        $disk = Storage::disk(EncryptedFiles::DISK);
        $fingerprint = CpfBlindIndex::fingerprint();

        if ($this->option('if-key-changed') && $disk->exists(self::FINGERPRINT_FILE)
            && trim((string) $disk->get(self::FINGERPRINT_FILE)) === $fingerprint) {
            $this->info('Chave do hash do CPF inalterada; nada a recalcular.');

            return self::SUCCESS;
        }

        $count = 0;

        try {
            // One transaction: a failure leaves every hash as it was.
            DB::transaction(function () use (&$count) {
                Patient::withTrashed()->lazyById(500)->each(function (Patient $patient) use (&$count) {
                    // saveQuietly: the hash is derived data, not a change to audit; timestamps stay untouched.
                    $patient->forceFill(['cpf_hash' => CpfBlindIndex::hash($patient->cpf)]);
                    $patient->timestamps = false;
                    $patient->saveQuietly();
                    $count++;
                });
            });
        } catch (DecryptException) {
            // Typically APP_KEY was changed: the CPFs (encrypted with the old key) can no longer be read.
            $this->error('Não foi possível descriptografar os CPFs com a APP_KEY atual. A APP_KEY não pode ser trocada '
                .'diretamente: volte a chave anterior (ou re-criptografe os dados antes de trocar). Nada foi alterado.');

            return self::FAILURE;
        }

        // Only after every row was updated: an interrupted run is retried on the next start.
        $disk->put(self::FINGERPRINT_FILE, $fingerprint);

        $this->info("{$count} paciente(s) atualizado(s).");

        return self::SUCCESS;
    }
}
