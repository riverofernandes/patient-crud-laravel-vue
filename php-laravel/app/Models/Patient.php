<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Patient extends Model
{
    use HasFactory;
    use SoftDeletes;

    public const SEXES = ['M', 'F'];

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
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        $term = trim((string) $term);

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
     * Escapes LIKE wildcards using "!" as escape character (portable across MySQL and SQLite).
     */
    private static function escapeLike(string $value): string
    {
        return str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $value);
    }
}
