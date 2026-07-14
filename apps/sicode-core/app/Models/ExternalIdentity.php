<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExternalIdentity extends CoreModel
{
    protected $fillable = [
        'provider',
        'provider_context',
        'external_subject',
        'status',
        'linked_at',
        'last_seen_at',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'linked_at' => 'datetime',
            'last_seen_at' => 'datetime',
        ];
    }
}
