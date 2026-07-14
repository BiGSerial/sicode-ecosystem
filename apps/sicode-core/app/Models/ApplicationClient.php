<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApplicationClient extends CoreModel
{
    protected $fillable = [
        'client_identifier',
        'name',
        'type',
        'status',
        'redirect_uris',
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
}
