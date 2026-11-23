<?php

namespace App\Http\Controllers;

use App\Http\Middleware\EnsureActiveChild;
use App\Models\Child;
use Illuminate\Http\Request;
use LogicException;

abstract class Controller
{
    /**
     * Get the active Child that the active-child middleware found for the request.
     */
    protected function activeChild(Request $request): Child
    {
        $child = $request->attributes->get(Child::class);

        if (! $child instanceof Child) {
            throw new LogicException('The route needs the active-child middleware ('.EnsureActiveChild::class.').');
        }

        return $child;
    }
}
