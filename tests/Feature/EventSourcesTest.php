<?php

use App\Enums\ParticipantRole;
use App\Enums\SourceKind;
use App\Models\Event;
use App\Models\Person;
use App\Models\Source;
use App\Models\User;

test('guests are redirected to the login page from event source routes', function () {
    $event = Event::factory()->create();
    $source = Source::factory()->create();

    $this->post(route('events.sources.store', $event), ['source_id' => $source->id])
        ->assertRedirect(route('login'));

    $this->delete(route('events.sources.destroy', [$event, $source]))
        ->assertRedirect(route('login'));
});

test('linking an own source to an own event creates the link, redirects back and flashes the toast', function () {
    $user = User::factory()->create();
    $event = Event::factory()->for($user->tree)->create();
    $source = Source::factory()->for($user->tree)->create();

    $response = $this->from(route('persons.index'))
        ->actingAs($user)
        ->post(route('events.sources.store', $event), ['source_id' => $source->id]);

    $response->assertRedirect(route('persons.index'));
    $response->assertSessionHas('inertia.flash_data.toast', ['type' => 'success', 'message' => 'Powiązano akt']);

    $this->assertDatabaseHas('event_source', ['event_id' => $event->id, 'source_id' => $source->id]);
});

test('linking the same source twice keeps a single link', function () {
    $user = User::factory()->create();
    $event = Event::factory()->for($user->tree)->create();
    $source = Source::factory()->for($user->tree)->create();

    $this->actingAs($user)->post(route('events.sources.store', $event), ['source_id' => $source->id]);
    $this->actingAs($user)->post(route('events.sources.store', $event), ['source_id' => $source->id]);

    $this->assertDatabaseCount('event_source', 1);
});

test('linking another trees source fails validation and creates nothing', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $event = Event::factory()->for($user->tree)->create();
    $foreignSource = Source::factory()->for($otherUser->tree)->create();

    $response = $this->actingAs($user)->post(route('events.sources.store', $event), ['source_id' => $foreignSource->id]);

    $response->assertSessionHasErrors(['source_id' => 'Zaznaczone pole źródło jest nieprawidłowe.']);
    $this->assertDatabaseCount('event_source', 0);
});

test('linking a non-metrical source fails with the akt message and creates nothing', function (SourceKind $kind) {
    $user = User::factory()->create();
    $event = Event::factory()->for($user->tree)->create();
    $source = Source::factory()->for($user->tree)->create(['kind' => $kind]);

    $response = $this->actingAs($user)->post(route('events.sources.store', $event), ['source_id' => $source->id]);

    $response->assertSessionHasErrors(['source_id' => 'Do wydarzenia można na razie powiązać tylko akt metrykalny.']);
    $this->assertDatabaseCount('event_source', 0);
})->with([SourceKind::Gravestone, SourceKind::Other]);

test('linking another trees gravestone gives the generic message, not the akt one', function () {
    $user = User::factory()->create();
    $event = Event::factory()->for($user->tree)->create();
    $source = Source::factory()->gravestone()->for(User::factory()->create()->tree)->create();

    $response = $this->actingAs($user)->post(route('events.sources.store', $event), ['source_id' => $source->id]);

    $response->assertSessionHasErrors(['source_id' => 'Zaznaczone pole źródło jest nieprawidłowe.']);
    $this->assertDatabaseCount('event_source', 0);
});

test('linking to another trees event returns not found', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $event = Event::factory()->for($otherUser->tree)->create();
    $source = Source::factory()->for($user->tree)->create();

    $this->actingAs($user)->post(route('events.sources.store', $event), ['source_id' => $source->id])
        ->assertNotFound();

    $this->assertDatabaseCount('event_source', 0);
});

test('linking to another trees event with a foreign source id also returns not found', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $event = Event::factory()->for($otherUser->tree)->create();
    $foreignSource = Source::factory()->for($otherUser->tree)->create();

    $this->actingAs($user)->post(route('events.sources.store', $event), ['source_id' => $foreignSource->id])
        ->assertNotFound();
});

test('linking to another trees event with a missing source id also returns not found', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $event = Event::factory()->for($otherUser->tree)->create();

    $this->actingAs($user)->post(route('events.sources.store', $event), [])
        ->assertNotFound();
});

test('unlinking removes the link', function () {
    $user = User::factory()->create();
    $event = Event::factory()->for($user->tree)->create();
    $source = Source::factory()->for($user->tree)->create();
    $event->sources()->attach($source);

    $response = $this->from(route('sources.show', $source))
        ->actingAs($user)
        ->delete(route('events.sources.destroy', [$event, $source]));

    $response->assertRedirect(route('sources.show', $source));
    $response->assertSessionHas('inertia.flash_data.toast', ['type' => 'success', 'message' => 'Odpięto źródło']);

    $this->assertDatabaseCount('event_source', 0);
});

test('unlinking a source that is not linked returns not found', function () {
    $user = User::factory()->create();
    $event = Event::factory()->for($user->tree)->create();
    $source = Source::factory()->for($user->tree)->create();

    $this->actingAs($user)->delete(route('events.sources.destroy', [$event, $source]))
        ->assertNotFound();
});

test('unlinking a source from another trees event returns not found', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $event = Event::factory()->for($otherUser->tree)->create();
    $source = Source::factory()->for($otherUser->tree)->create();
    $event->sources()->attach($source);

    $this->actingAs($user)->delete(route('events.sources.destroy', [$event, $source]))
        ->assertNotFound();

    $this->assertDatabaseCount('event_source', 1);
});

test('one source linked to two events confirms both', function () {
    $user = User::factory()->create();
    $personA = Person::factory()->for($user->tree)->create();
    $personB = Person::factory()->for($user->tree)->create();
    $birthOfA = Event::factory()->for($user->tree)->birth()->create();
    $deathOfB = Event::factory()->for($user->tree)->death()->create();
    $birthOfA->participants()->attach($personA, ['role' => ParticipantRole::Subject]);
    $deathOfB->participants()->attach($personB, ['role' => ParticipantRole::Subject]);
    $source = Source::factory()->for($user->tree)->create();

    $this->actingAs($user)->post(route('events.sources.store', $birthOfA), ['source_id' => $source->id]);
    $this->actingAs($user)->post(route('events.sources.store', $deathOfB), ['source_id' => $source->id]);

    expect($source->events()->count())->toBe(2);

    $this->actingAs($user)->get(personPreviewUrl($personA))
        ->assertInertia(fn ($page) => $page->where('personPreview.person.birth.is_confirmed', true));

    $this->actingAs($user)->get(personPreviewUrl($personB))
        ->assertInertia(fn ($page) => $page->where('personPreview.person.death.is_confirmed', true));
});

test('unlinking another trees source from an own event returns not found', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $event = Event::factory()->for($user->tree)->create();
    $foreignSource = Source::factory()->for($otherUser->tree)->create();
    $event->sources()->attach($foreignSource);

    $this->actingAs($user)->delete(route('events.sources.destroy', [$event, $foreignSource]))
        ->assertNotFound();

    $this->assertDatabaseCount('event_source', 1);
});

test('deleting a source cascades the pivot rows', function () {
    $user = User::factory()->create();
    $event = Event::factory()->for($user->tree)->create();
    $source = Source::factory()->for($user->tree)->create();
    $event->sources()->attach($source);

    $source->delete();

    $this->assertDatabaseCount('event_source', 0);
});

test('deleting an event cascades the pivot rows', function () {
    $user = User::factory()->create();
    $event = Event::factory()->for($user->tree)->create();
    $source = Source::factory()->for($user->tree)->create();
    $event->sources()->attach($source);

    $event->delete();

    $this->assertDatabaseCount('event_source', 0);
});
