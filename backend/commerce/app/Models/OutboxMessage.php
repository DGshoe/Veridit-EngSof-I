<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Transactional outbox (ADR-011). Call record() inside the same DB transaction
 * as the business effect, so the event exists if and only if the effect does.
 *
 * @property string $id
 * @property string $type
 * @property array $payload
 * @property Carbon $occurred_at
 * @property ?Carbon $published_at
 * @property int $attempts
 */
class OutboxMessage extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $fillable = ['type', 'payload', 'occurred_at', 'published_at', 'attempts', 'last_error'];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'occurred_at' => 'datetime',
            'published_at' => 'datetime',
            'attempts' => 'integer',
        ];
    }

    public static function record(string $type, array $payload): self
    {
        $message = new self(['type' => $type, 'occurred_at' => now()]);
        $message->id = $message->newUniqueId();
        $message->payload = ['event_id' => $message->id, 'type' => $type] + $payload;
        $message->save();

        return $message;
    }
}
