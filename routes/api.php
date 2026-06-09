<?php

use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\AtemArciController;
use App\Http\Controllers\AtemAttachmentController;
use App\Http\Controllers\AtemController;
use App\Http\Controllers\AtemProgressController;
use App\Http\Controllers\AtemReferenceLinkController;
use App\Http\Controllers\AtemStatusController;
use App\Http\Controllers\IncentiveRuleController;
use App\Http\Controllers\LevelStructureController;
use App\Http\Controllers\TableauApiController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::post('/login', [AuthController::class, 'login'])->name('login');

Route::middleware('auth:api')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/me',      [AuthController::class, 'me'])->name('me');

    // ATEM lookups
    Route::get('/atem/lookups',  [AtemController::class, 'lookups']);
    Route::get('/atem/levels',   [LevelStructureController::class, 'index']);
    Route::get('/atem/rules',    [IncentiveRuleController::class, 'index']);
    Route::get('/atem/statuses', [AtemStatusController::class, 'index']);

    // ATEM cards
    Route::get('/atem',       [AtemController::class, 'index']);
    Route::post('/atem',      [AtemController::class, 'store']);
    Route::get('/atem/{id}',    [AtemController::class, 'show'])->whereNumber('id');
    Route::put('/atem/{id}',    [AtemController::class, 'update'])->whereNumber('id');
    Route::delete('/atem/{id}', [AtemController::class, 'destroy'])->whereNumber('id');

    // ATEM ARCI members
    Route::post('/atem/{id}/arci',                [AtemArciController::class, 'store'])->whereNumber('id');
    Route::delete('/atem/{id}/arci',              [AtemArciController::class, 'destroy'])->whereNumber('id');
    Route::delete('/atem/{id}/arci/role/{role}',  [AtemArciController::class, 'destroyByRole'])->whereNumber('id');

    // ATEM reference links
    Route::get('/atem/{id}/reference-links',             [AtemReferenceLinkController::class, 'index'])->whereNumber('id');
    Route::post('/atem/{id}/reference-links',            [AtemReferenceLinkController::class, 'store'])->whereNumber('id');
    Route::delete('/atem/{id}/reference-links/{linkId}', [AtemReferenceLinkController::class, 'destroy'])->whereNumber('id')->whereNumber('linkId');

    // ATEM progress updates
    Route::get('/atem/{id}/progress',                    [AtemProgressController::class, 'index'])->whereNumber('id');
    Route::post('/atem/{id}/progress',                   [AtemProgressController::class, 'store'])->whereNumber('id');
    Route::put('/atem/{id}/progress/{progressId}',       [AtemProgressController::class, 'update'])->whereNumber('id')->whereNumber('progressId');
    Route::delete('/atem/{id}/progress/{progressId}',    [AtemProgressController::class, 'destroy'])->whereNumber('id')->whereNumber('progressId');

    // ATEM attachments
    Route::get('/atem/{id}/attachments',                    [AtemAttachmentController::class, 'index'])->whereNumber('id');
    Route::post('/atem/{id}/attachments',                   [AtemAttachmentController::class, 'store'])->whereNumber('id');
    Route::delete('/atem/{id}/attachments/{attId}',         [AtemAttachmentController::class, 'destroy'])->whereNumber('id')->whereNumber('attId');
    Route::get('/atem/{id}/attachments/{attId}/download',   [AtemAttachmentController::class, 'download'])->whereNumber('id')->whereNumber('attId');
});

Route::get('/tableau/view-data', [TableauApiController::class, 'viewData']);
