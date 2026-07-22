<?php

namespace App\Contracts;

use App\Models\Note;

interface AdsSubmissionPolicy
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function validateSubmission(Note $note, array $payload): void;

    public function isAdsClosed(Note $note): bool;
}
