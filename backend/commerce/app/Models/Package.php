<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property string $code
 * @property string $name
 * @property int $credits
 * @property int $price_cents
 * @property bool $active
 */
class Package extends Model
{
    use HasUuids;

    protected $fillable = ['code', 'name', 'credits', 'price_cents', 'active'];

    protected function casts(): array
    {
        return [
            'credits' => 'integer',
            'price_cents' => 'integer',
            'active' => 'boolean',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('active', true);
    }
}
