<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

class Patient extends Model
{
    use HasFactory;
    use SoftDeletes;

    public const SEXES = ['M', 'F'];

    /** Columns the listing may be sorted by (whitelist). */
    public const SORTABLE = ['name', 'birth_date', 'created_at'];

    public const DEFAULT_SORT = 'name';

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

    protected $casts = [
        'birth_date' => 'date',
    ];

    /**
     * Filter by partial name or CPF (with or without mask).
     */
    public function scopeSearch(Builder $query, mixed $term): Builder
    {
        $term = is_string($term) ? trim($term) : '';

        if ($term === '') {
            return $query;
        }

        // Only search the CPF column when the term looks like a (partial, maybe masked) CPF.
        $digits = preg_match('/^[\d.\-\s]+$/', $term) ? preg_replace('/\D/', '', $term) : '';

        return $query->where(function (Builder $query) use ($term, $digits) {
            $query->whereRaw("name LIKE ? ESCAPE '!'", ['%'.self::escapeLike($term).'%']);

            if ($digits !== '') {
                $query->orWhere('cpf', 'like', "%{$digits}%");
            }
        });
    }

    /**
     * Order by a whitelisted column/direction (invalid values fall back to name/asc), always tie-breaking by id asc.
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
     */
    public function scopeActiveWithCpf(Builder $query, string $cpf, int $ignoreId = null): Builder
    {
        return $query->withoutTrashed()
            ->where('cpf', $cpf)
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

    /**
     * Escapes LIKE wildcards using "!" as escape character (portable across MySQL and SQLite).
     */
    private static function escapeLike(string $value): string
    {
        return str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $value);
    }
}
