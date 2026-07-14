<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

abstract class CoreModel extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    /**
     * Eloquent only hydrates database-generated keys in its incrementing insert path.
     * CORE keeps UUID generation in PostgreSQL, so missing primary keys must use the
     * same RETURNING-based insert while preserving non-incrementing string keys.
     */
    protected function performInsert(Builder $query): bool
    {
        if ($this->usesUniqueIds()) {
            $this->setUniqueIds();
        }

        if ($this->fireModelEvent('creating') === false) {
            return false;
        }

        if ($this->usesTimestamps()) {
            $this->updateTimestamps();
        }

        $attributes = $this->getAttributesForInsert();
        $keyName = $this->getKeyName();

        if ($attributes === []) {
            return true;
        }

        if (! array_key_exists($keyName, $attributes) || $attributes[$keyName] === null) {
            $this->insertAndSetId($query, $attributes);
        } else {
            $query->insert($attributes);
        }

        $this->exists = true;
        $this->wasRecentlyCreated = true;

        $this->fireModelEvent('created', false);

        return true;
    }
}
