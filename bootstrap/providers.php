<?php

use App\Medical\Providers\MedicalServiceProvider;
use App\Providers\AppServiceProvider;
use App\Providers\FortifyServiceProvider;

return [
    AppServiceProvider::class,
    FortifyServiceProvider::class,
    MedicalServiceProvider::class,
];
