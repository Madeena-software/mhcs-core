<?php

use App\Modules\Doctor\DoctorServiceProvider;
use App\Modules\ImageGateway\ImageGatewayServiceProvider;
use App\Modules\Member\MemberServiceProvider;
use App\Modules\Operator\OperatorServiceProvider;
use App\Providers\AppServiceProvider;
use App\Providers\Filament\AdminPanelProvider;

return [
    AppServiceProvider::class,
    AdminPanelProvider::class,
    MemberServiceProvider::class,
    OperatorServiceProvider::class,
    DoctorServiceProvider::class,
    ImageGatewayServiceProvider::class,
];
