<?php

namespace App\Models;

use App\Support\CpfBlindIndex;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property string $cpf
 * @property string $cpf_hash
 * @property string $rg
 * @property Carbon|null $birth_date
 * @property string $sex
 * @property string $phone
 * @property string|null $email
 * @property string|null $description
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 *
 * @method static Builder<static> search(mixed $term)
 * @method static Builder<static> ofSex(mixed $sex)
 * @method static Builder<static> ageBetween(mixed $min, mixed $max)
 * @method static Builder<static> sorted(mixed $sort, mixed $direction)
 * @method static Builder<static> activeWithCpf(string $cpf, int|null $ignoreId = null)
 */
class Patient extends Model
{
    use HasFactory;
    use SoftDeletes;

    public const SEXES = ['M', 'F'];

    /** Columns the listing may be sorted by (whitelist). */
    public const SORTABLE = ['name', 'birth_date', 'created_at'];

    public const DEFAULT_SORT = 'name';

    /** Accepted range for the age_min / age_max filters (outside it the filter is ignored). */
    public const MIN_AGE_FILTER = 0;

    public const MAX_AGE_FILTER = 150;

    protected $fillable = [
        'name',
        'cpf',
        'rg',
        'birth_date',
        'sex',
        'phone',
        'email',
        'description',
    ];

    protected $hidden = [
        'cpf_hash',
    ];

    protected $casts = [
        'birth_date' => 'date',
        // Encrypted at rest with APP_KEY; CPF lookups go through cpf_hash (blind index).
        'cpf' => 'encrypted',
        'rg' => 'encrypted',
        'phone' => 'encrypted',
    ];

    protected static function booted(): void
    {
        // Keeps the blind index in sync with the CPF (encrypted casts compare decrypted values, so
        // re-saving the same CPF is not "dirty").
        static::saving(function (Patient $patient) {
            if ($patient->isDirty('cpf') || $patient->cpf_hash === null) {
                $patient->cpf_hash = CpfBlindIndex::hash((string) $patient->cpf);
            }
        });
    }

    /**
     * @return HasMany<Consultation>
     */
    public function consultations(): HasMany
    {
        return $this->hasMany(Consultation::class);
    }

    /**
     * @return HasMany<PatientAudit>
     */
    public function audits(): HasMany
    {
        return $this->hasMany(PatientAudit::class);
    }

    /**
     * Filter by partial name, or by a FULL CPF (11 digits, with or without mask) through the blind index.
     * A partial CPF cannot be searched: the column is encrypted.
     *
     * @param  Builder<Patient>  $query
     * @return Builder<Patient>
     */
    public function scopeSearch(Builder $query, mixed $term): Builder
    {
        $term = is_string($term) ? trim($term) : '';

        if ($term === '') {
            return $query;
        }

        $digits = preg_match('/^[\d.\-\s]+$/', $term) ? (string) preg_replace('/\D/', '', $term) : '';

        return $query->where(function (Builder $query) use ($term, $digits) {
            $query->whereRaw("name LIKE ? ESCAPE '!'", ['%'.self::escapeLike($term).'%']);

            if (strlen($digits) === 11) {
                $query->orWhere('cpf_hash', CpfBlindIndex::hash($digits));
            }
        });
    }

    /**
     * Filter by sex (`M` or `F`); any other value is ignored.
     *
     * @param  Builder<Patient>  $query
     * @return Builder<Patient>
     */
    public function scopeOfSex(Builder $query, mixed $sex): Builder
    {
        return in_array($sex, self::SEXES, true) ? $query->where('sex', $sex) : $query;
    }

    /**
     * Filter by age in whole years (America/Sao_Paulo), translated to birth_date ranges so the index is used:
     * age >= min  <=> birth_date <= today - min years;  age <= max  <=> birth_date > today - (max + 1) years.
     * Invalid bounds (not an integer in 0–150) are ignored.
     *
     * @param  Builder<Patient>  $query
     * @return Builder<Patient>
     */
    public function scopeAgeBetween(Builder $query, mixed $min, mixed $max): Builder
    {
        $min = self::parseAge($min);
        $max = self::parseAge($max);
        $today = Carbon::now(config('app.timezone'))->startOfDay();

        // NoOverflow: on Feb 29, "one year ago" is Feb 28 (not Mar 1), matching ageOn().
        // Half-open bounds on the next day (`< D+1`, `>= D+1`) instead of `<= D` / `> D` keep the column
        // index usable and also hold when SQLite stores the date as "Y-m-d 00:00:00".
        return $query
            ->when($min !== null, fn (Builder $query) => $query->where(
                'birth_date', '<', $today->copy()->subYearsNoOverflow((int) $min)->addDay()->toDateString()
            ))
            ->when($max !== null, fn (Builder $query) => $query->where(
                'birth_date', '>=', $today->copy()->subYearsNoOverflow((int) $max + 1)->addDay()->toDateString()
            ));
    }

    /**
     * Order by a whitelisted column/direction (invalid values fall back to name/asc), always tie-breaking by id asc.
     *
     * @param  Builder<Patient>  $query
     * @return Builder<Patient>
     */
    public function scopeSorted(Builder $query, mixed $sort, mixed $direction): Builder
    {
        $sort = in_array($sort, self::SORTABLE, true) ? $sort : self::DEFAULT_SORT;
        $direction = $direction === 'desc' ? 'desc' : 'asc';

        return $query->orderBy($sort, $direction)->orderBy('id');
    }

    /**
     * Active (not soft-deleted) patients using the given CPF, optionally excluding one id.
     * Single source of truth for CPF uniqueness (validation, availability check and restore).
     *
     * @param  Builder<Patient>  $query
     * @return Builder<Patient>
     */
    public function scopeActiveWithCpf(Builder $query, string $cpf, int $ignoreId = null): Builder
    {
        return $query->withoutTrashed()
            ->where('cpf_hash', CpfBlindIndex::hash($cpf))
            ->when($ignoreId !== null, fn (Builder $query) => $query->whereKeyNot($ignoreId));
    }

    public static function cpfInUse(string $cpf, int $ignoreId = null): bool
    {
        return static::query()->activeWithCpf($cpf, $ignoreId)->exists();
    }

    /**
     * Age in whole years, computed on the current date in the app timezone (America/Sao_Paulo).
     */
    public function ageOn(CarbonInterface $date = null): ?int
    {
        if ($this->birth_date === null) {
            return null;
        }

        $today = ($date ?? Carbon::now())->copy()->setTimezone(config('app.timezone'));
        $birth = $this->birth_date;

        return $today->year - $birth->year - ($today->format('md') < $birth->format('md') ? 1 : 0);
    }

    private static function parseAge(mixed $value): ?int
    {
        if (! is_string($value) && ! is_int($value)) {
            return null;
        }

        $age = filter_var($value, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => self::MIN_AGE_FILTER, 'max_range' => self::MAX_AGE_FILTER],
        ]);

        return $age === false ? null : $age;
    }

    /**
     * Escapes LIKE wildcards using "!" as escape character (portable across MySQL and SQLite).
     */
    private static function escapeLike(string $value): string
    {
        return str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $value);
    }
}
