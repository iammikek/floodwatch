<?php

namespace App\Livewire;

use App\Models\SystemActivity;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.flood-watch')]
class ActivityFeed extends Component
{
    public function render()
    {
        $activities = SystemActivity::recent(100);

        return view('livewire.activity-feed', [
            'activities' => $activities,
        ]);
    }
}
