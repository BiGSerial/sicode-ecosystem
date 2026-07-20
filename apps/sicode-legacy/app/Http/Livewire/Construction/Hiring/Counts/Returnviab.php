<?php

namespace App\Http\Livewire\Construction\Hiring\Counts;

use App\Models\Note;
use Livewire\Component;

class Returnviab extends Component
{
    public function getCountProperty()
    {
        return Note::whereRelation('Viabilities', function ($q) {
            $q->where('engineer', true)
                ->where(function ($q) {
                    $q->where('rejected', true)
                    ->orwhere('approved', true);
                })->where('hired', false);
        })->count();
    }


    public function render()
    {
        return view('livewire.construction.hiring.counts.returnviab', [
            'count' => $this->count
        ]);
    }
}
