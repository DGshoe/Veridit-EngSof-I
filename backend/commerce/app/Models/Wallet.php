<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property string $user_id
 * @property int $balance
 */
class Wallet extends Model
{
    protected $primaryKey = 'user_id';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = ['user_id', 'balance'];

    protected function casts(): array
    {
        return ['balance' => 'integer'];
    }
}
