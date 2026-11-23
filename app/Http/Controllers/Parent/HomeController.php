<?php

namespace App\Http\Controllers\Parent;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The Parent area, opened from the picker without a PIN.
 */
class HomeController extends Controller
{
    /**
     * Show the Parent's Children with ways to add, edit and delete them.
     */
    public function __invoke(Request $request): Response
    {
        return Inertia::render('parent/home', [
            'childProfiles' => $request->user()->children()->orderBy('id')->get(['id', 'name', 'grade', 'avatar']),
        ]);
    }
}
