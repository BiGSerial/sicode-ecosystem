<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property CarbonInterface|null $starts_at
 * @property CarbonInterface|null $ends_at
 */
class Contract extends CoreModel
{
    protected $fillable = [
        'identifier',
        'status',
        'starts_at',
        'ends_at',
    ];

    /**
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * @return HasMany<ContractApplicationGrant, $this>
     */
    public function applicationGrants(): HasMany
    {
        return $this->hasMany(ContractApplicationGrant::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }
}
