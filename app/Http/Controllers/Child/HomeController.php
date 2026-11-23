<?php

namespace App\Http\Controllers\Child;

use App\Http\Controllers\Controller;
use App\Models\Child;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The active Child's home screen.
 */
class HomeController extends Controller
{
    /**
     * Show the active Child's home screen.
     */
    public function __invoke(Request $request): Response
    {
        /** @var Child $child */
        $child = $request->attributes->get(Child::class);

        return Inertia::render('child/home', [
            'child' => $child->only(['id', 'name', 'grade', 'avatar']),
        ]);
    }
}
