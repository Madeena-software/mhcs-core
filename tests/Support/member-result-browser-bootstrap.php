<?php

declare(strict_types=1);

use App\Modules\Doctor\Application\Contracts\ClinicalSourceInventory;
use Tests\Support\ClosedSyntheticClinicalInventory;

$app = require __DIR__.'/radiologist-browser-bootstrap.php';
$inventory = storage_path('framework/testing/radiologist-browser/member-inventory.json');
if (is_file($inventory)) {
    $app->instance(ClinicalSourceInventory::class, new ClosedSyntheticClinicalInventory(json_decode(file_get_contents($inventory), true, flags: JSON_THROW_ON_ERROR)));
}

return $app;
