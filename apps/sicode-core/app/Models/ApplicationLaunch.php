<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApplicationLaunch extends CoreModel
{
    protected $fillable = [
        'token_hash',
        'state_hash',
        'callback_url',
        'authorized_organization_id',
        'issued_at',
        'expires_at',
        'consumed_at',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

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
     * @return BelongsTo<Organization, $this>
     */
    public function authorizedOrganization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'authorized_organization_id');
    }

    /**
     * @return BelongsTo<ApplicationClient, $this>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(ApplicationClient::class, 'client_id');
    }

    /**
     * @return BelongsTo<ApplicationClient, $this>
     */
    public function consumedByClient(): BelongsTo
    {
        return $this->belongsTo(ApplicationClient::class, 'consumed_by_client_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'issued_at' => 'datetime',
            'expires_at' => 'datetime',
            'consumed_at' => 'datetime',
        ];
    }
}
