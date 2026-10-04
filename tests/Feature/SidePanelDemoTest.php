<?php

use App\Models\User;

test('the demo page is not found outside the local environment', function () {
    $this->actingAs(User::factory()->create())->get(route('dev.side-panel'))->assertNotFound();
});

test('the demo page renders the items without an open panel in local', function () {
    app()->detectEnvironment(fn () => 'local');

    $this->actingAs(User::factory()->create())->get(route('dev.side-panel'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('dev/side-panel')
            ->has('items')
            ->where('demoPanel', null));
});

test('an existing demo id opens that record in the panel', function () {
    app()->detectEnvironment(fn () => 'local');

    $this->actingAs(User::factory()->create())->get(route('dev.side-panel', ['demo' => 2]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('demoPanel.id', 2));
});

test('an unknown demo id is a 404', function () {
    app()->detectEnvironment(fn () => 'local');

    $this->actingAs(User::factory()->create())->get(route('dev.side-panel', ['demo' => 999]))->assertNotFound();
});

test('guests are redirected to the login page', function () {
    $this->get(route('dev.side-panel'))->assertRedirect(route('login'));
});
