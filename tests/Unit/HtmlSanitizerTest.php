<?php

use App\Support\HtmlSanitizer;

/*
| Sanitizing rules from INT-JB-04 / CAT-03 / ART-02: no <script>, <iframe>,
| on* attributes; text, heading, list, link, image, and table markup is kept.
*/

beforeEach(fn () => $this->sanitizer = new HtmlSanitizer);

test('keeps text, heading, list, link, image, and table markup', function () {
    $html = '<h2>Spesifikasi</h2><p>Motor <strong>500W</strong><br>baterai 48V</p><ul><li>Satu</li></ul>'
        .'<a href="https://example.com/a" title="Info">tautan</a><img src="https://cdn.example.com/a.png" alt="foto">'
        .'<table><tr><th>Warna</th><td>Hitam</td></tr></table>';

    $clean = $this->sanitizer->clean($html);

    expect($clean)->toContain('<h2>Spesifikasi</h2>', '<strong>500W</strong>', '<li>Satu</li>', 'href="https://example.com/a"', '<img src="https://cdn.example.com/a.png" alt="foto"', '<th>Warna</th>', '<td>Hitam</td>');
});

test('removes scripts, iframes, event handlers, and styles', function (string $dirty, array $absent) {
    $clean = $this->sanitizer->clean($dirty);

    foreach ($absent as $needle) {
        expect(strtolower($clean))->not->toContain($needle);
    }
    expect($clean)->toContain('aman');
})->with([
    'script' => ['<p>aman</p><script>alert(1)</script>', ['<script', 'alert']],
    'iframe' => ['<p>aman</p><iframe src="https://evil.example"></iframe>', ['<iframe', 'evil.example']],
    'onerror' => ['<p>aman</p><img src="x.png" onerror="alert(1)">', ['onerror', 'alert']],
    'onclick' => ['<p onclick="steal()">aman</p>', ['onclick', 'steal']],
    'style' => ['<p style="position:fixed">aman</p><style>p{display:none}</style>', ['style', 'position', 'display']],
    'javascript url' => ['<p>aman</p><a href="javascript:alert(1)">klik</a>', ['javascript:']],
    'data url' => ['<p>aman</p><img src="data:text/html;base64,PHNjcmlwdD4=">', ['data:']],
    'form' => ['<p>aman</p><form action="https://evil.example"><input name="x"></form>', ['<form', '<input']],
]);

test('links get rel noopener when they open a new window', function () {
    expect($this->sanitizer->clean('<a href="https://example.com" target="_blank">x</a>'))->toContain('noopener')->toContain('noreferrer');
});

test('plain Jubelio descriptions pass through unchanged in meaning', function () {
    $html = '<p>Baterai <strong>lithium</strong> 48V 12Ah.</p>';

    expect($this->sanitizer->clean($html))->toBe($html);
});

test('null and blank input give an empty string', function (?string $input) {
    expect($this->sanitizer->clean($input))->toBe('');
})->with([null, '', "  \n "]);
