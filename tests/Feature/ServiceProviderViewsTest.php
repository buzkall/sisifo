<?php

use Illuminate\Support\Facades\Artisan;

test('does not register a views namespace pointing at a missing directory', function() {
    $paths = app('view')->getFinder()->getHints()['sisifo'] ?? [];

    $missing = array_values(array_filter($paths, fn(string $path): bool => ! is_dir($path)));

    expect($missing)->toBe([]);
});

test('view:cache succeeds even though the package ships no views', function() {
    expect(Artisan::call('view:cache'))->toBe(0);

    Artisan::call('view:clear');
});
