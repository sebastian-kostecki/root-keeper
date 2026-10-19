<?php

use App\Models\User;

test('a guest on the home route is redirected to the login screen', function () {
    $this->get('/')->assertRedirect('/login');
});

test('a signed-in user opening the login screen is redirected into the app', function () {
    $this->actingAs(User::factory()->create())
        ->get('/login')
        ->assertRedirect('/tree');
});

test('a signed-in user on the home route ends up on the tree', function () {
    $this->actingAs(User::factory()->create())
        ->followingRedirects()
        ->get('/')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('tree/show'));
});
