<?php

use Arzcode\Sisifo\Support\PushoverHtml;

test('renders the allowed Pushover formatting tags', function() {
    $result = PushoverHtml::sanitize('<b>DICTAPP</b><br><i>note</i><hr><u>x</u>');

    expect($result)->toBe('<b>DICTAPP</b><br><i>note</i><hr><u>x</u>');
});

test('normalizes self-closing break and rule variants', function() {
    expect(PushoverHtml::sanitize('a<br/>b<br />c<hr/>d'))
        ->toBe('a<br>b<br>c<hr>d');
});

test('converts plain newlines to line breaks', function() {
    expect(PushoverHtml::sanitize("line 1\nline 2"))
        ->toContain('<br')
        ->and(PushoverHtml::sanitize("line 1\nline 2"))->toContain('line 1')
        ->and(PushoverHtml::sanitize("line 1\nline 2"))->toContain('line 2');
});

test('keeps http and https anchors and forces safe attributes', function() {
    $result = PushoverHtml::sanitize('<a href="https://example.com?a=1&b=2">link</a>');

    expect($result)->toBe('<a href="https://example.com?a=1&amp;b=2" target="_blank" rel="noopener noreferrer">link</a>');
});

test('escapes script tags and other disallowed markup', function() {
    expect(PushoverHtml::sanitize('<script>alert(1)</script>'))
        ->toBe('&lt;script&gt;alert(1)&lt;/script&gt;');
});

test('only bare formatting tags are allowed, so attributes never survive', function() {
    $result = PushoverHtml::sanitize('<b onclick="alert(1)">x</b>');

    expect($result)->toContain('&lt;b onclick=&quot;alert(1)&quot;&gt;')
        ->and($result)->not->toContain('onclick="');
});

test('refuses non http anchor schemes', function() {
    $result = PushoverHtml::sanitize('<a href="javascript:alert(1)">x</a>');

    expect($result)->not->toContain('<a ')
        ->and($result)->toContain('&lt;a href=&quot;javascript:alert(1)&quot;&gt;');
});
