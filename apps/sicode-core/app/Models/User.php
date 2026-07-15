<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class User extends CoreModel
{
    protected $fillable = [
        'display_name',
        'primary_email',
        'primary_email_normalized',
        'status',
    ];

    /**
     * @return HasMany<ExternalIdentity, $this>
     */
    public function externalIdentities(): HasMany
    {
        return $this->hasMany(ExternalIdentity::class);
    }

    /**
     * @return HasOne<LocalPasswordCredential, $this>
     */
    public function localPasswordCredential(): HasOne
    {
        return $this->hasOne(LocalPasswordCredential::class);
    }

    /**
     * @return HasMany<OrganizationMembership, $this>
     */
    public function organizationMemberships(): HasMany
    {
        return $this->hasMany(OrganizationMembership::class);
    }

    /**
     * @return HasMany<ApplicationAccess, $this>
     */
    public function applicationAccesses(): HasMany
    {
        return $this->hasMany(ApplicationAccess::class);
    }

    /**
     * @return HasMany<ApplicationLaunch, $this>
     */
    public function applicationLaunches(): HasMany
    {
        return $this->hasMany(ApplicationLaunch::class);
    }
}
