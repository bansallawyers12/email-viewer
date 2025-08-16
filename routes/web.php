<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\EmailController;
use App\Http\Controllers\AttachmentController;
use App\Http\Controllers\UploadController;
use App\Http\Controllers\LabelController;
use Illuminate\Support\Facades\URL;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

// Main application route
Route::get('/', function () {
    return view('app');
});

// API routes for the frontend
Route::prefix('api/emails')->group(function () {
    Route::get('/', [EmailController::class, 'index']);
    // Clear all emails for current user (used by LeftPanel quick action) - must be before {id} routes
    Route::delete('/clear-all', [EmailController::class, 'clearAll']);
    Route::get('/{id}', [EmailController::class, 'show']);
    Route::put('/{id}', [EmailController::class, 'update']);
    Route::delete('/{id}', [EmailController::class, 'destroy']);
    Route::get('/{id}/statistics', [EmailController::class, 'statistics']);
    Route::get('/{id}/export-pdf', [EmailController::class, 'exportPdf']);
    Route::get('/{id}/download-pdf', [EmailController::class, 'downloadPdf']);
    Route::get('/{id}/download-html', [EmailController::class, 'downloadHtml']);
});

// Attachment routes
Route::prefix('api/attachments')->group(function () {
    Route::get('/email/{emailId}', [AttachmentController::class, 'index']);
    Route::get('/{id}', [AttachmentController::class, 'show']);
    Route::get('/{id}/download', [AttachmentController::class, 'download']);
    Route::get('/{id}/preview', [AttachmentController::class, 'preview']);
    Route::get('/email/{emailId}/download-all', [AttachmentController::class, 'downloadAll']);
    Route::get('/email/{emailId}/statistics', [AttachmentController::class, 'statistics']);
});

// Label routes
Route::prefix('api/labels')->group(function () {
    Route::get('/', [LabelController::class, 'index']);
    Route::post('/', [LabelController::class, 'store']);
    Route::put('/{id}', [LabelController::class, 'update']);
    Route::delete('/{id}', [LabelController::class, 'destroy']);
    Route::post('/apply', [LabelController::class, 'applyToEmail']);
    Route::delete('/remove', [LabelController::class, 'removeFromEmail']);
    Route::get('/{id}/emails', [LabelController::class, 'getEmailsByLabel']);
});

// Upload routes
Route::prefix('api/upload')->group(function () {
    Route::post('/', [UploadController::class, 'upload']);
    Route::get('/progress/{emailId}', [UploadController::class, 'progress']);
    Route::get('/storage-usage', [UploadController::class, 'storageUsage']);
    Route::delete('/{emailId}', [UploadController::class, 'delete']);
});

// Fallback route for SPA
Route::fallback(function () {
    return view('app');
});
