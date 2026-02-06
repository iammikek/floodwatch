<?php

namespace App\Events;

use App\Models\SystemActivity;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class InfrastructureStatusChanged
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public SystemActivity $activity
    ) {}
}
