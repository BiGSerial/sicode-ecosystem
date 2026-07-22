<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CoreIdentityLink extends Model
{
    use HasUuids;

    public const STATUS_ACTIVE = 'active';
    public const STATUS_SUSPENDED = 'suspended';
    public const STATUS_REVOKED = 'revoked';

    protected $fillable = [
        'core_issuer',
        'core_subject',
        'legacy_user_id',
        'application_context',
        'status',
        'linked_at',
        'last_used_at',
    ];

    protected $casts = [
        'linked_at' => 'datetime',
        'last_used_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'legacy_user_id')->withTrashed();
    }
}
