<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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

    /**
     * @return HasMany<ApplicationLaunch, $this>
     */
    public function launches(): HasMany
    {
        return $this->hasMany(ApplicationLaunch::class, 'client_id');
    }

    /**
     * @return list<string>
     */
    public function redirectUris(): array
    {
        $value = $this->getAttribute('redirect_uris');

        if (is_array($value)) {
            return array_values(array_filter($value, 'is_string'));
        }

        if (! is_string($value) || $value === '') {
            return [];
        }

        $trimmed = trim($value, '{}');

        if ($trimmed === '') {
            return [];
        }

        return array_values(array_filter(
            str_getcsv($trimmed, ',', '"', '\\'),
            fn (string $uri): bool => $uri !== '',
        ));
    }
}
