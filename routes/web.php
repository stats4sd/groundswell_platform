<?php

use App\Http\Controllers\DownloadController;
use App\Http\Controllers\TempResultsController;
use App\Livewire\CoverPage;
use App\Livewire\ResultsPage;
use Illuminate\Support\Facades\Route;

// redirect user from root path to app panel login page
Route::get('/', CoverPage::class)->name('cover-page');
Route::get('/results', ResultsPage::class)->name('results');


// when user logout from program admin panel, redirect user to app panel login pager
Route::get('/program/login', function () {
    return redirect('app');
})->name('filament.program.auth.login');

// when user logout from admin panel, redirect user to app panel login pager
Route::get('/admin/login', function () {
    return redirect('app');
})->name('filament.admin.auth.login');

// TODO: add additional authentication layer to ensure users cannot access downloads for a different team, etc.
Route::get('/downloads/{file}', [DownloadController::class, 'download'])
    ->where('file', '.*')
    ->middleware('auth');

Route::get('/app/profile', function () {
    return redirect('/app/' . Auth::user()->latest_team_id . '/profile');

})->middleware('auth')
    ->name('filament.app.auth.profile');


Route::get('/temp-results', [TempResultsController::class, 'index']);
