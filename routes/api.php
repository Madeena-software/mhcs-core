<?php

declare(strict_types=1);

use App\Http\Controllers\Grabber\GrabberDicomUploadController;
use App\Http\Controllers\Grabber\GrabberManifestController;
use App\Http\Middleware\AuthenticateGrabberClient;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/grabber')->middleware([AuthenticateGrabberClient::class])->group(function (): void {
    Route::get('/manifest/{code}', [GrabberManifestController::class, 'manifestByCode'])->name('api.grabber.manifest.by-code');
    Route::get('/radiography-sessions/{code}/manifest', [GrabberManifestController::class, 'manifestByCode'])->name('api.grabber.radiography-session.manifest');
    Route::post('/manifest/lookup', [GrabberManifestController::class, 'lookupManifest'])->name('api.grabber.manifest.lookup');

    Route::post('/radiography-sessions/{code}/dicom', [GrabberDicomUploadController::class, 'upload'])->name('api.grabber.radiography-session.dicom.upload');
    Route::post('/dicom/upload', [GrabberDicomUploadController::class, 'uploadByBody'])->name('api.grabber.dicom.upload');
    Route::post('/dicom', [GrabberDicomUploadController::class, 'uploadByBody'])->name('api.grabber.dicom');
    Route::post('/upload', [GrabberDicomUploadController::class, 'uploadByBody'])->name('api.grabber.upload');
});
