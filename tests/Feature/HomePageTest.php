<?php

test('the home page returns a successful response', function () {
    $response = $this->withoutVite()->get(route('home'));

    $response->assertOk();
    $response->assertSee(config('app.name'));
});
