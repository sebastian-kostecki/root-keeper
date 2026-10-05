<?php

use App\Models\Locality;
use App\Models\Parish;
use App\Models\Person;
use App\Models\Source;
use App\Models\User;

test('the shell counts the people and sources of the user own tree', function () {
    $user = User::factory()->create();
    Person::factory()->count(3)->for($user->tree)->create();
    Source::factory()->count(2)->for($user->tree)->create();

    $this->actingAs($user)->get('/persons')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('navCounts.people', 3)
            ->where('navCounts.sources', 2));
});

test('the shell counts do not include another users people and sources', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    Person::factory()->for($user->tree)->create();
    Person::factory()->count(2)->for($otherUser->tree)->create();
    Source::factory()->count(4)->for($otherUser->tree)->create();

    $this->actingAs($user)->get('/persons')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('navCounts.people', 1)
            ->where('navCounts.sources', 0));
});

test('the shell counts localities of the own tree only', function () {
    $user = User::factory()->create();
    Locality::factory()->count(2)->for($user->tree)->create();
    Locality::factory()->count(3)->for(User::factory()->create()->tree)->create();

    $this->actingAs($user)->get('/persons')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('navCounts.localities', 2));
});

test('the shell counts parishes of the own tree only', function () {
    $user = User::factory()->create();
    Parish::factory()->count(2)->for($user->tree)->create();
    Parish::factory()->count(3)->for(User::factory()->create()->tree)->create();

    $this->actingAs($user)->get('/persons')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('navCounts.parishes', 2));
});

test('the shell counts are null for a guest', function () {
    $this->get('/login')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('navCounts', null));
});
