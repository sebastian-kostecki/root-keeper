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

    $this->patch(route('events.sources.update', [$event, $source]), ['entry_number' => '1'])
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
    $response->assertSessionHas('inertia.flash_data.toast', ['type' => 'success', 'message' => 'Powiązano źródło']);

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

test('linking a source of any kind creates the link', function (SourceKind $kind) {
    $user = User::factory()->create();
    $event = Event::factory()->for($user->tree)->create();
    $source = Source::factory()->for($user->tree)->create(['kind' => $kind]);

    $response = $this->actingAs($user)->post(route('events.sources.store', $event), ['source_id' => $source->id]);

    $response->assertSessionDoesntHaveErrors();
    $this->assertDatabaseHas('event_source', ['event_id' => $event->id, 'source_id' => $source->id]);
    $this->assertDatabaseCount('event_source', 1);
})->with(SourceKind::cases());

test('linking another trees gravestone fails validation and creates nothing', function () {
    $user = User::factory()->create();
    $event = Event::factory()->for($user->tree)->create();
    $source = Source::factory()->gravestone()->for(User::factory()->create()->tree)->create();

    $response = $this->actingAs($user)->post(route('events.sources.store', $event), ['source_id' => $source->id]);

    $response->assertSessionHasErrors(['source_id' => 'Zaznaczone pole źródło jest nieprawidłowe.']);
    $this->assertDatabaseCount('event_source', 0);
});

test('linking to another trees event with a foreign source id also returns not found', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $event = Event::factory()->for($otherUser->tree)->create();
    $foreignSource = Source::factory()->for($otherUser->tree)->create();

    $this->actingAs($user)->post(route('events.sources.store', $event), ['source_id' => $foreignSource->id])
        ->assertNotFound();

    $this->assertDatabaseCount('event_source', 0);
});

test('linking to another trees event with a missing source id also returns not found', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $event = Event::factory()->for($otherUser->tree)->create();

    $this->actingAs($user)->post(route('events.sources.store', $event), [])
        ->assertNotFound();

    $this->assertDatabaseCount('event_source', 0);
});

test('unlinking removes the link', function () {
    $user = User::factory()->create();
    $event = Event::factory()->for($user->tree)->create();
    $source = Source::factory()->for($user->tree)->create();
    $event->sources()->attach($source);

    $response = $this->from(route('sources.index', ['source' => $source->id]))
        ->actingAs($user)
        ->delete(route('events.sources.destroy', [$event, $source]));

    $response->assertRedirect(route('sources.index', ['source' => $source->id]));
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

test('linking with an entry number stores it on the link', function () {
    $user = User::factory()->create();
    $event = Event::factory()->for($user->tree)->create();
    $source = Source::factory()->for($user->tree)->create();

    $this->actingAs($user)->post(route('events.sources.store', $event), ['source_id' => $source->id, 'entry_number' => '95'])
        ->assertSessionHasNoErrors();

    $this->assertDatabaseHas('event_source', ['event_id' => $event->id, 'source_id' => $source->id, 'entry_number' => '95']);
});

test('linking without an entry number leaves it empty', function () {
    $user = User::factory()->create();
    $event = Event::factory()->for($user->tree)->create();
    $source = Source::factory()->for($user->tree)->create();

    $this->actingAs($user)->post(route('events.sources.store', $event), ['source_id' => $source->id, 'entry_number' => ''])
        ->assertSessionHasNoErrors();

    $this->assertDatabaseHas('event_source', ['event_id' => $event->id, 'source_id' => $source->id, 'entry_number' => null]);
});

test('linking an already linked source with another entry number keeps the first one', function () {
    $user = User::factory()->create();
    $event = Event::factory()->for($user->tree)->create();
    $source = Source::factory()->for($user->tree)->create();

    $this->actingAs($user)->post(route('events.sources.store', $event), ['source_id' => $source->id, 'entry_number' => '95']);
    $this->actingAs($user)->post(route('events.sources.store', $event), ['source_id' => $source->id, 'entry_number' => '96'])
        ->assertSessionHasNoErrors();

    $this->assertDatabaseCount('event_source', 1);
    $this->assertDatabaseHas('event_source', ['event_id' => $event->id, 'source_id' => $source->id, 'entry_number' => '95']);
});

test('an entry number longer than 50 characters fails validation and creates nothing', function () {
    $user = User::factory()->create();
    $event = Event::factory()->for($user->tree)->create();
    $source = Source::factory()->for($user->tree)->create();

    $this->actingAs($user)->post(route('events.sources.store', $event), ['source_id' => $source->id, 'entry_number' => str_repeat('9', 51)])
        ->assertSessionHasErrors('entry_number');

    $this->assertDatabaseCount('event_source', 0);
});

test('updating sets and clears the entry number of a link', function () {
    $user = User::factory()->create();
    $event = Event::factory()->for($user->tree)->create();
    $source = Source::factory()->for($user->tree)->create();
    $event->sources()->attach($source);

    $response = $this->from(route('persons.index'))
        ->actingAs($user)
        ->patch(route('events.sources.update', [$event, $source]), ['entry_number' => '95']);

    $response->assertRedirect(route('persons.index'));
    $response->assertSessionHas('inertia.flash_data.toast', ['type' => 'success', 'message' => 'Zapisano numer wpisu']);
    $this->assertDatabaseHas('event_source', ['event_id' => $event->id, 'source_id' => $source->id, 'entry_number' => '95']);

    $this->actingAs($user)->patch(route('events.sources.update', [$event, $source]), ['entry_number' => ''])
        ->assertSessionHasNoErrors();

    $this->assertDatabaseHas('event_source', ['event_id' => $event->id, 'source_id' => $source->id, 'entry_number' => null]);
});

test('updating an entry number longer than 50 characters fails validation', function () {
    $user = User::factory()->create();
    $event = Event::factory()->for($user->tree)->create();
    $source = Source::factory()->for($user->tree)->create();
    $event->sources()->attach($source, ['entry_number' => '1']);

    $this->actingAs($user)->patch(route('events.sources.update', [$event, $source]), ['entry_number' => str_repeat('9', 51)])
        ->assertSessionHasErrors('entry_number');

    $this->assertDatabaseHas('event_source', ['event_id' => $event->id, 'source_id' => $source->id, 'entry_number' => '1']);
});

test('updating the entry number of a pair that is not linked returns not found', function () {
    $user = User::factory()->create();
    $event = Event::factory()->for($user->tree)->create();
    $source = Source::factory()->for($user->tree)->create();

    $this->actingAs($user)->patch(route('events.sources.update', [$event, $source]), ['entry_number' => '95'])
        ->assertNotFound();
});

test('the person preview carries the entry number of each linked source', function () {
    $user = User::factory()->create();
    $person = Person::factory()->for($user->tree)->create();
    $birth = Event::factory()->for($user->tree)->birth()->create();
    $birth->participants()->attach($person, ['role' => ParticipantRole::Subject]);
    $source = Source::factory()->for($user->tree)->create();
    $birth->sources()->attach($source, ['entry_number' => '95']);

    $this->actingAs($user)->get(personPreviewUrl($person))
        ->assertInertia(fn ($page) => $page->where('personPreview.person.birth.sources.0.entry_number', '95'));
});

test('linking a source to a burial returns not found and creates nothing', function () {
    $user = User::factory()->create();
    $burial = Event::factory()->for($user->tree)->burial()->withLocality()->create();
    $source = Source::factory()->for($user->tree)->create();

    $this->actingAs($user)->post(route('events.sources.store', $burial), ['source_id' => $source->id])
        ->assertNotFound();

    $this->assertDatabaseCount('event_source', 0);
});

test('updating an entry number on a burial returns not found', function () {
    $user = User::factory()->create();
    $burial = Event::factory()->for($user->tree)->burial()->create();
    $source = Source::factory()->for($user->tree)->create();

    $this->actingAs($user)->patch(route('events.sources.update', [$burial, $source]), ['entry_number' => '95'])
        ->assertNotFound();
});
