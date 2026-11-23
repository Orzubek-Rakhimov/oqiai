<?php

use App\Http\Controllers\Child\HomeController as ChildHomeController;
use App\Http\Controllers\Child\ReadingController;
use App\Http\Controllers\Child\RetryController;
use App\Http\Controllers\Child\SentenceController;
use App\Http\Controllers\Child\StoryController;
use App\Http\Controllers\Child\SyllableHelpController;
use App\Http\Controllers\Parent\ChildController;
use App\Http\Controllers\Parent\HomeController as ParentHomeController;
use App\Http\Controllers\Parent\MicrophoneCheckController;
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
    Route::get('parent/children/{child}/microphone', [MicrophoneCheckController::class, 'show'])->name('children.microphone');
    Route::post('parent/children/{child}/microphone', [MicrophoneCheckController::class, 'store'])
        ->middleware('throttle:speech-to-text')
        ->name('children.microphone.store');

    Route::middleware(['active-child'])->group(function () {
        Route::get('child', ChildHomeController::class)->name('child.home');
        Route::get('child/stories', [StoryController::class, 'index'])->name('stories.index');
        Route::post('child/stories/{story}/readings', [ReadingController::class, 'store'])->name('readings.store');
        Route::get('child/readings/{reading}', [ReadingController::class, 'show'])->name('readings.show');
        Route::get('child/readings/{reading}/result', [ReadingController::class, 'result'])->name('readings.result');
        Route::middleware(['throttle:speech-to-text'])->group(function () {
            Route::post('child/readings/{reading}/sentences', [SentenceController::class, 'store'])->name('readings.sentences.store');
            Route::post('child/readings/{reading}/retries', [RetryController::class, 'store'])->name('readings.retries.store');
        });
        Route::post('child/readings/{reading}/syllable-help', [SyllableHelpController::class, 'store'])->name('readings.syllable-help.store');
    });
});

require __DIR__.'/settings.php';
