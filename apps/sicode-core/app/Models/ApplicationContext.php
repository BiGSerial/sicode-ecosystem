<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ApplicationContext extends CoreModel
{
    protected $fillable = [
        'code',
        'name',
        'status',
        'requires_organization',
        'requires_contract',
    ];

    /**
     * @return BelongsTo<Application, $this>
     */
    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    /**
     * @return HasMany<ApplicationClient, $this>
     */
    public function clients(): HasMany
    {
        return $this->hasMany(ApplicationClient::class, 'context_id');
    }

    /**
     * @return HasMany<ApplicationAccess, $this>
     */
    public function accesses(): HasMany
    {
        return $this->hasMany(ApplicationAccess::class, 'context_id');
    }

    /**
     * @return HasMany<ContractApplicationGrant, $this>
     */
    public function contractGrants(): HasMany
    {
        return $this->hasMany(ContractApplicationGrant::class, 'context_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'requires_organization' => 'boolean',
            'requires_contract' => 'boolean',
        ];
    }
}
