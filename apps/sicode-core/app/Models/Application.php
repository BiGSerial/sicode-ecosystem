<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

class Application extends CoreModel
{
    protected $fillable = [
        'code',
        'name',
        'status',
        'requires_organization',
        'requires_contract',
    ];

    /**
     * @return HasMany<ApplicationContext, $this>
     */
    public function contexts(): HasMany
    {
        return $this->hasMany(ApplicationContext::class);
    }

    /**
     * @return HasMany<ApplicationClient, $this>
     */
    public function clients(): HasMany
    {
        return $this->hasMany(ApplicationClient::class);
    }

    /**
     * @return HasMany<ApplicationAccess, $this>
     */
    public function accesses(): HasMany
    {
        return $this->hasMany(ApplicationAccess::class);
    }

    /**
     * @return HasMany<ContractApplicationGrant, $this>
     */
    public function contractGrants(): HasMany
    {
        return $this->hasMany(ContractApplicationGrant::class);
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
