<?php

use App\Models\Story;

test('the launch stories load cleanly, three per level', function () {
    $this->artisan('stories:load')->assertSuccessful();

    expect(Story::count())->toBe(9);
    expect(Story::orderBy('level')->pluck('level')->countBy()->all())
        ->toBe([1 => 3, 2 => 3, 3 => 3]);
});
