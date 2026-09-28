<?php

use App\Enums\ParticipantRole;
use App\Models\Event;
use App\Models\Person;
use App\Models\Scan;
use App\Models\User;

test('guests are redirected to the login page from event scan routes', function () {
    $event = Event::factory()->create();
    $scan = Scan::factory()->create();

    $this->post(route('events.scans.store', $event), ['scan_id' => $scan->id])
        ->assertRedirect(route('login'));

    $this->delete(route('events.scans.destroy', [$event, $scan]))
        ->assertRedirect(route('login'));
});

test('linking an own scan to an own event creates the link, redirects back and flashes the toast', function () {
    $user = User::factory()->create();
    $event = Event::factory()->for($user->tree)->create();
    $scan = Scan::factory()->for($user->tree)->create();

    $response = $this->from(route('persons.index'))
        ->actingAs($user)
        ->post(route('events.scans.store', $event), ['scan_id' => $scan->id]);

    $response->assertRedirect(route('persons.index'));
    $response->assertSessionHas('inertia.flash_data.toast', ['type' => 'success', 'message' => 'Powiązano skan']);

    $this->assertDatabaseHas('event_scan', ['event_id' => $event->id, 'scan_id' => $scan->id]);
});

test('linking the same scan twice keeps a single link', function () {
    $user = User::factory()->create();
    $event = Event::factory()->for($user->tree)->create();
    $scan = Scan::factory()->for($user->tree)->create();

    $this->actingAs($user)->post(route('events.scans.store', $event), ['scan_id' => $scan->id]);
    $this->actingAs($user)->post(route('events.scans.store', $event), ['scan_id' => $scan->id]);

    $this->assertDatabaseCount('event_scan', 1);
});

test('linking another trees scan fails validation and creates nothing', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $event = Event::factory()->for($user->tree)->create();
    $foreignScan = Scan::factory()->for($otherUser->tree)->create();

    $response = $this->actingAs($user)->post(route('events.scans.store', $event), ['scan_id' => $foreignScan->id]);

    $response->assertSessionHasErrors('scan_id');
    $this->assertDatabaseCount('event_scan', 0);
});

test('linking to another trees event returns not found', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $event = Event::factory()->for($otherUser->tree)->create();
    $scan = Scan::factory()->for($user->tree)->create();

    $this->actingAs($user)->post(route('events.scans.store', $event), ['scan_id' => $scan->id])
        ->assertNotFound();

    $this->assertDatabaseCount('event_scan', 0);
});

test('linking to another trees event with a foreign scan id also returns not found', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $event = Event::factory()->for($otherUser->tree)->create();
    $foreignScan = Scan::factory()->for($otherUser->tree)->create();

    $this->actingAs($user)->post(route('events.scans.store', $event), ['scan_id' => $foreignScan->id])
        ->assertNotFound();
});

test('linking to another trees event with a missing scan id also returns not found', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $event = Event::factory()->for($otherUser->tree)->create();

    $this->actingAs($user)->post(route('events.scans.store', $event), [])
        ->assertNotFound();
});

test('unlinking removes the link', function () {
    $user = User::factory()->create();
    $event = Event::factory()->for($user->tree)->create();
    $scan = Scan::factory()->for($user->tree)->create();
    $event->scans()->attach($scan);

    $response = $this->from(route('scans.show', $scan))
        ->actingAs($user)
        ->delete(route('events.scans.destroy', [$event, $scan]));

    $response->assertRedirect(route('scans.show', $scan));
    $response->assertSessionHas('inertia.flash_data.toast', ['type' => 'success', 'message' => 'Odpięto skan']);

    $this->assertDatabaseCount('event_scan', 0);
});

test('unlinking a scan that is not linked returns not found', function () {
    $user = User::factory()->create();
    $event = Event::factory()->for($user->tree)->create();
    $scan = Scan::factory()->for($user->tree)->create();

    $this->actingAs($user)->delete(route('events.scans.destroy', [$event, $scan]))
        ->assertNotFound();
});

test('unlinking a scan from another trees event returns not found', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $event = Event::factory()->for($otherUser->tree)->create();
    $scan = Scan::factory()->for($otherUser->tree)->create();
    $event->scans()->attach($scan);

    $this->actingAs($user)->delete(route('events.scans.destroy', [$event, $scan]))
        ->assertNotFound();

    $this->assertDatabaseCount('event_scan', 1);
});

test('one scan linked to two events confirms both', function () {
    $user = User::factory()->create();
    $personA = Person::factory()->for($user->tree)->create();
    $personB = Person::factory()->for($user->tree)->create();
    $birthOfA = Event::factory()->for($user->tree)->birth()->create();
    $deathOfB = Event::factory()->for($user->tree)->death()->create();
    $birthOfA->participants()->attach($personA, ['role' => ParticipantRole::Subject]);
    $deathOfB->participants()->attach($personB, ['role' => ParticipantRole::Subject]);
    $scan = Scan::factory()->for($user->tree)->create();

    $this->actingAs($user)->post(route('events.scans.store', $birthOfA), ['scan_id' => $scan->id]);
    $this->actingAs($user)->post(route('events.scans.store', $deathOfB), ['scan_id' => $scan->id]);

    expect($scan->events()->count())->toBe(2);

    $this->actingAs($user)->get(personPreviewUrl($personA))
        ->assertInertia(fn ($page) => $page->where('personPreview.person.birth.is_confirmed', true));

    $this->actingAs($user)->get(personPreviewUrl($personB))
        ->assertInertia(fn ($page) => $page->where('personPreview.person.death.is_confirmed', true));
});

test('unlinking another trees scan from an own event returns not found', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $event = Event::factory()->for($user->tree)->create();
    $foreignScan = Scan::factory()->for($otherUser->tree)->create();
    $event->scans()->attach($foreignScan);

    $this->actingAs($user)->delete(route('events.scans.destroy', [$event, $foreignScan]))
        ->assertNotFound();

    $this->assertDatabaseCount('event_scan', 1);
});

test('deleting a scan cascades the pivot rows', function () {
    $user = User::factory()->create();
    $event = Event::factory()->for($user->tree)->create();
    $scan = Scan::factory()->for($user->tree)->create();
    $event->scans()->attach($scan);

    $scan->delete();

    $this->assertDatabaseCount('event_scan', 0);
});

test('deleting an event cascades the pivot rows', function () {
    $user = User::factory()->create();
    $event = Event::factory()->for($user->tree)->create();
    $scan = Scan::factory()->for($user->tree)->create();
    $event->scans()->attach($scan);

    $event->delete();

    $this->assertDatabaseCount('event_scan', 0);
});
