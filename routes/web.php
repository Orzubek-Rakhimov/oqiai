<?php

use App\Http\Controllers\Child\HomeController as ChildHomeController;
use App\Http\Controllers\Child\StoryController;
use App\Http\Controllers\Parent\ChildController;
use App\Http\Controllers\Parent\HomeController as ParentHomeController;
use App\Http\Controllers\PickerController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

Route::middleware(['auth'])->group(function () {
    Route::get('picker', [PickerController::class, 'show'])->name('picker');
    Route::post('picker/{child}', [PickerController::class, 'choose'])->name('picker.choose');

    Route::get('parent', ParentHomeController::class)->name('parent.home');
    Route::resource('parent/children', ChildController::class)
        ->except(['index', 'show'])
        ->names('children')
        ->parameters(['children' => 'child']);

    Route::middleware(['active-child'])->group(function () {
        Route::get('child', ChildHomeController::class)->name('child.home');
        Route::get('child/stories', [StoryController::class, 'index'])->name('stories.index');
    });
});

require __DIR__.'/settings.php';
