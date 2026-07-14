<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LocalPasswordCredential extends CoreModel
{
    protected $fillable = [
        'status',
        'password_changed_at',
        'invalidated_at',
    ];

    protected $hidden = [
        'password_hash',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isActive(): bool
    {
        return $this->status === LocalPasswordCredentialStatus::Active->value
            && $this->invalidated_at === null;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'password_changed_at' => 'datetime',
            'invalidated_at' => 'datetime',
        ];
    }
}
