<?php

use App\Providers\AppServiceProvider;
use App\Providers\TelescopeServiceProvider;
use Laravel\Telescope\TelescopeApplicationServiceProvider;

return [
    AppServiceProvider::class,
    ...(class_exists(TelescopeApplicationServiceProvider::class)
        ? [TelescopeServiceProvider::class]
        : []),
];
