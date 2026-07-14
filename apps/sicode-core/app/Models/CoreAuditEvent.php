<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CoreAuditEvent extends CoreModel
{
    public $timestamps = false;

    protected $fillable = [
        'occurred_at',
        'actor_type',
        'actor_id',
        'action',
        'subject_type',
        'subject_id',
        'application_id',
        'context_id',
        'reason',
        'correlation_id',
        'details',
    ];

    /**
     * @return BelongsTo<Application, $this>
     */
    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    /**
     * @return BelongsTo<ApplicationContext, $this>
     */
    public function context(): BelongsTo
    {
        return $this->belongsTo(ApplicationContext::class, 'context_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
            'details' => 'array',
        ];
    }
}
