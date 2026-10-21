<?php

use App\Enums\ParishKind;
use App\Enums\Religion;
use App\Models\Locality;
use App\Models\Parish;
use App\Models\Source;
use App\Models\Tree;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Storage;

test('guests are redirected to the login page from parishes routes', function () {
    $parish = Parish::factory()->create();

    $this->get(route('parishes.index'))->assertRedirect(route('login'));
    $this->post(route('parishes.store'), [])->assertRedirect(route('login'));
    $this->put(route('parishes.update', $parish), [])->assertRedirect(route('login'));
    $this->postJson(route('parishes.inline.store'), [])->assertUnauthorized();
});

test('storing creates a parish with kind, seat, religion and dedication and redirects to its panel with the toast', function () {
    $user = User::factory()->create();
    $seat = Locality::factory()->for($user->tree)->create();

    $response = $this->actingAs($user)->post(route('parishes.store'), [
        'kind' => 'parish',
        'locality_id' => $seat->id,
        'religion' => 'roman_catholic',
        'dedication' => '  św. Jana ',
    ]);

    $parish = $user->tree->parishes()->firstOrFail();

    $response
        ->assertRedirect(route('places.index', ['kind' => 'parafie', 'parish' => $parish->id]))
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Dodano parafię / urząd']);

    expect($parish->name)->toBeNull();
    expect($parish->kind)->toBe(ParishKind::Parish);
    expect($parish->dedication)->toBe('św. Jana');
    expect($parish->religion)->toBe(Religion::RomanCatholic);
    expect($parish->locality_id)->toBe($seat->id);
});

test('a blank dedication is stored as null', function () {
    $user = User::factory()->create();
    $seat = Locality::factory()->for($user->tree)->create();

    $this->actingAs($user)->post(route('parishes.store'), ['kind' => 'civil_registry', 'locality_id' => $seat->id, 'dedication' => '   '])
        ->assertSessionHasNoErrors();

    expect($user->tree->parishes()->sole()->dedication)->toBeNull();
});

test('updating changes the fields, redirects back with the toast and clears religion and dedication', function () {
    $user = User::factory()->create();
    $seat = Locality::factory()->for($user->tree)->create();
    $newSeat = Locality::factory()->for($user->tree)->create();
    $parish = Parish::factory()->for($user->tree)->withReligion(Religion::Orthodox)->seatedIn($seat)->dedicatedTo('Jana')->create();

    $this->actingAs($user)->from(route('places.index', ['kind' => 'parafie', 'parish' => $parish->id]))
        ->put(route('parishes.update', $parish), ['kind' => 'civil_registry', 'locality_id' => $newSeat->id, 'religion' => '', 'dedication' => ''])
        ->assertRedirect(route('places.index', ['kind' => 'parafie', 'parish' => $parish->id]))
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Zapisano parafię / urząd']);

    $parish->refresh();

    expect($parish->kind)->toBe(ParishKind::CivilRegistry);
    expect($parish->dedication)->toBeNull();
    expect($parish->religion)->toBeNull();
    expect($parish->locality_id)->toBe($newSeat->id);
});

test('a parish may keep its own key when updated', function () {
    $user = User::factory()->create();
    $parish = Parish::factory()->for($user->tree)->withReligion(Religion::Orthodox)->dedicatedTo('Św. Jana')->create();

    $this->actingAs($user)->put(route('parishes.update', $parish), [
        'kind' => 'parish',
        'locality_id' => $parish->locality_id,
        'religion' => 'orthodox',
        'dedication' => 'św. jana',
    ])->assertSessionHasNoErrors();

    expect($parish->refresh()->dedication)->toBe('św. jana');
});

test('the same seat, kind, religion and dedication is rejected ignoring case and spaces', function () {
    $user = User::factory()->create();
    $seat = Locality::factory()->for($user->tree)->create(['name' => 'Płock']);
    Parish::factory()->for($user->tree)->seatedIn($seat)->dedicatedTo('św. Bartłomieja')->withReligion(Religion::RomanCatholic)->create();

    $this->actingAs($user)->post(route('parishes.store'), [
        'kind' => 'parish',
        'locality_id' => $seat->id,
        'religion' => 'roman_catholic',
        'dedication' => '  ŚW. BARTŁOMIEJA ',
    ])->assertInvalid(['dedication' => 'Ta parafia / urząd już istnieje: „Płock św. Bartłomieja (rzym.-kat.)”.']);

    expect($user->tree->parishes()->count())->toBe(1);
});

test('two records without a dedication in one seat and kind collide, unless the religion differs', function () {
    $user = User::factory()->create();
    $seat = Locality::factory()->for($user->tree)->create();
    Parish::factory()->for($user->tree)->seatedIn($seat)->create();

    $this->actingAs($user)->post(route('parishes.store'), ['kind' => 'parish', 'locality_id' => $seat->id])->assertInvalid('dedication');
    $this->actingAs($user)->post(route('parishes.store'), ['kind' => 'parish', 'locality_id' => $seat->id, 'religion' => 'orthodox'])
        ->assertSessionHasNoErrors();

    expect($user->tree->parishes()->count())->toBe(2);
});

test('a parish and a civil registry office in the same seat are two records', function () {
    $user = User::factory()->create();
    $seat = Locality::factory()->for($user->tree)->create();
    Parish::factory()->for($user->tree)->seatedIn($seat)->create();

    $this->actingAs($user)->post(route('parishes.store'), ['kind' => 'civil_registry', 'locality_id' => $seat->id])
        ->assertSessionHasNoErrors();

    expect($user->tree->parishes()->count())->toBe(2);
});

test('the same key in another tree is allowed', function () {
    $user = User::factory()->create();
    $seat = Locality::factory()->for($user->tree)->create();
    Parish::factory()->for(User::factory()->create()->tree)->dedicatedTo('Św. Jana')->create();

    $this->actingAs($user)->post(route('parishes.store'), ['kind' => 'parish', 'locality_id' => $seat->id, 'dedication' => 'Św. Jana'])
        ->assertSessionHasNoErrors();

    expect($user->tree->parishes()->count())->toBe(1);
});

test('the database refuses a duplicate key even when validation is skipped', function () {
    $user = User::factory()->create();
    $seat = Locality::factory()->for($user->tree)->create();
    Parish::factory()->for($user->tree)->seatedIn($seat)->dedicatedTo('Fara')->create();

    expect(fn () => Parish::factory()->for($user->tree)->seatedIn($seat)->dedicatedTo('FARA')->create())
        ->toThrow(UniqueConstraintViolationException::class);
});

test('storing and updating validate the fields', function (array $payload, string $field) {
    $user = User::factory()->create();
    $seat = Locality::factory()->for($user->tree)->create();
    $parish = Parish::factory()->for($user->tree)->create();
    $payload = array_map(fn (mixed $value): mixed => $value === ':seat' ? $seat->id : $value, $payload);

    $this->actingAs($user)->post(route('parishes.store'), $payload)->assertInvalid($field);
    $this->actingAs($user)->put(route('parishes.update', $parish), $payload)->assertInvalid($field);

    expect($user->tree->parishes()->count())->toBe(1);
})->with([
    'missing seat' => [['kind' => 'parish'], 'locality_id'],
    'blank seat' => [['kind' => 'parish', 'locality_id' => ''], 'locality_id'],
    'non-numeric seat' => [['kind' => 'parish', 'locality_id' => 'abc'], 'locality_id'],
    'missing kind' => [['locality_id' => ':seat'], 'kind'],
    'unknown kind' => [['kind' => 'abbey', 'locality_id' => ':seat'], 'kind'],
    'long dedication' => [['kind' => 'parish', 'locality_id' => ':seat', 'dedication' => str_repeat('a', 256)], 'dedication'],
    'unknown religion' => [['kind' => 'parish', 'locality_id' => ':seat', 'religion' => 'pastafarian'], 'religion'],
    'other kind without dedication' => [['kind' => 'other', 'locality_id' => ':seat'], 'dedication'],
]);

test('the other kind needs a dedication', function () {
    $user = User::factory()->create();
    $seat = Locality::factory()->for($user->tree)->create();

    $this->actingAs($user)->post(route('parishes.store'), ['kind' => 'other', 'locality_id' => $seat->id])
        ->assertInvalid(['dedication' => 'Podaj wezwanie lub wyróżnik dla rodzaju „inny”.']);
    $this->actingAs($user)->post(route('parishes.store'), ['kind' => 'other', 'locality_id' => $seat->id, 'dedication' => 'Konsystorz'])
        ->assertSessionHasNoErrors();
});

test('a seat from another tree is a validation error and nothing is saved', function () {
    $user = User::factory()->create();
    $foreignLocality = Locality::factory()->for(User::factory()->create()->tree)->create();
    $parish = Parish::factory()->for($user->tree)->create();
    $seatId = $parish->locality_id;

    $this->actingAs($user)->post(route('parishes.store'), ['kind' => 'parish', 'locality_id' => $foreignLocality->id])->assertInvalid('locality_id');
    $this->actingAs($user)->put(route('parishes.update', $parish), ['kind' => 'parish', 'locality_id' => $foreignLocality->id])->assertInvalid('locality_id');
    $this->actingAs($user)->postJson(route('parishes.inline.store'), ['kind' => 'parish', 'locality_id' => $foreignLocality->id])->assertUnprocessable();

    expect($user->tree->parishes()->count())->toBe(1);
    expect($parish->refresh()->locality_id)->toBe($seatId);
});

test('another user parish answers 404 on update, delete and merge', function () {
    $user = User::factory()->create();
    $foreign = Parish::factory()->for(User::factory()->create()->tree)->create();
    $seat = Locality::factory()->for($user->tree)->create();

    $this->actingAs($user)->put(route('parishes.update', $foreign), ['kind' => 'parish', 'locality_id' => $seat->id])->assertNotFound();
    $this->actingAs($user)->delete(route('parishes.destroy', $foreign))->assertNotFound();
    $this->actingAs($user)->post(route('parishes.merge', $foreign), ['target_id' => 1])->assertNotFound();
});

test('clear_name clears the former name and a name sent in the request is ignored', function () {
    $user = User::factory()->create();
    $parish = Parish::factory()->for($user->tree)->dedicatedTo('Fara')->create(['name' => 'Parafia w Lubomlu']);
    $payload = ['kind' => 'parish', 'locality_id' => $parish->locality_id, 'dedication' => 'Fara', 'name' => 'Podmieniona'];

    $this->actingAs($user)->put(route('parishes.update', $parish), $payload)->assertSessionHasNoErrors();

    expect($parish->refresh()->name)->toBe('Parafia w Lubomlu');

    $this->actingAs($user)->put(route('parishes.update', $parish), [...$payload, 'clear_name' => true])->assertSessionHasNoErrors();

    expect($parish->refresh()->name)->toBeNull();
});

test('the label is built from the seat, kind, dedication and religion', function (Closure $make, string $label) {
    expect($make()->load('locality')->label())->toBe($label);
})->with([
    'parish with dedication and religion' => [
        fn () => Parish::factory()->seatedIn(Locality::factory()->create(['name' => 'Płock']))->dedicatedTo('św. Bartłomieja')->withReligion(Religion::RomanCatholic)->create(),
        'Płock św. Bartłomieja (rzym.-kat.)',
    ],
    'civil registry' => [
        fn () => Parish::factory()->seatedIn(Locality::factory()->create(['name' => 'Gostynin']))->ofKind(ParishKind::CivilRegistry)->create(),
        'Gostynin USC',
    ],
    'jewish district' => [
        fn () => Parish::factory()->seatedIn(Locality::factory()->create(['name' => 'Andrzejewo']))->ofKind(ParishKind::JewishDistrict)->create(),
        'Andrzejewo okręg bóżniczy',
    ],
    'other kind with a dedication' => [
        fn () => Parish::factory()->seatedIn(Locality::factory()->create(['name' => 'Płock']))->ofKind(ParishKind::Other)->dedicatedTo('Konsystorz')->create(),
        'Płock Konsystorz',
    ],
    'legacy row without a seat' => [
        fn () => Parish::factory()->legacy('Parafia w Lubomlu')->withReligion(Religion::RomanCatholic)->for(Tree::factory())->create(),
        'Parafia w Lubomlu (rzym.-kat.)',
    ],
]);

test('the inline store returns the parish as 201 JSON with its label and a duplicate is a 422 with a dedication error', function () {
    $user = User::factory()->create();
    $seat = Locality::factory()->for($user->tree)->create(['name' => 'Tarnów']);
    $payload = ['kind' => 'parish', 'religion' => 'roman_catholic', 'locality_id' => $seat->id, 'dedication' => 'Katedralna'];

    $this->actingAs($user)->postJson(route('parishes.inline.store'), $payload)
        ->assertCreated()
        ->assertJsonPath('data.label', 'Tarnów Katedralna (rzym.-kat.)')
        ->assertJsonPath('data.locality_name', 'Tarnów')
        ->assertJsonPath('data.kind', 'parish')
        ->assertJsonPath('data.dedication', 'Katedralna')
        ->assertJsonStructure(['data' => ['id', 'name', 'kind', 'kind_label', 'dedication', 'religion', 'religion_label', 'locality_id', 'locality_name', 'label']]);

    $this->actingAs($user)->postJson(route('parishes.inline.store'), [...$payload, 'dedication' => 'KATEDRALNA'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('dedication');

    expect($user->tree->parishes()->count())->toBe(1);
});

test('deleting a user whose parish has a seat succeeds and removes the tree parishes and localities', function () {
    $user = User::factory()->create();
    $seat = Locality::factory()->for($user->tree)->create();
    Parish::factory()->for($user->tree)->seatedIn($seat)->create();
    $treeId = $user->tree->id;

    $user->delete();

    expect(Parish::query()->where('tree_id', $treeId)->count())->toBe(0);
    expect(Locality::query()->where('tree_id', $treeId)->count())->toBe(0);
});

test('guests are redirected to the login page from parish delete and merge', function () {
    $parish = Parish::factory()->create();

    $this->delete(route('parishes.destroy', $parish))->assertRedirect(route('login'));
    $this->post(route('parishes.merge', $parish), [])->assertRedirect(route('login'));
});

test('an unused parish can be deleted and the user goes back to the list with the toast', function () {
    $user = User::factory()->create();
    $parish = Parish::factory()->for($user->tree)->create();

    $this->actingAs($user)->delete(route('parishes.destroy', $parish))
        ->assertRedirect(route('places.index', ['kind' => 'parafie']))
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Usunięto parafię / urząd']);

    expect(Parish::find($parish->id))->toBeNull();
});

test('a parish that an act still names cannot be deleted', function () {
    Storage::fake('local');

    $user = User::factory()->create();
    $parish = Parish::factory()->for($user->tree)->create();
    Source::factory()->for($user->tree)->create(['parish_id' => $parish->id]);

    $this->actingAs($user)->delete(route('parishes.destroy', $parish))
        ->assertSessionHasErrors(['parish' => 'Parafia / urząd jest używany — scal go z innym zamiast usuwać.']);

    expect(Parish::find($parish->id))->not->toBeNull();
});

test('merging moves every act of the duplicate to the target and deletes the duplicate', function () {
    Storage::fake('local');

    $user = User::factory()->create();
    $duplicate = Parish::factory()->for($user->tree)->legacy('Lubomla')->create();
    $target = Parish::factory()->for($user->tree)->legacy('Luboml')->create();
    $other = Parish::factory()->for($user->tree)->create();
    $movedAct = Source::factory()->for($user->tree)->create(['parish_id' => $duplicate->id]);
    $ownAct = Source::factory()->for($user->tree)->create(['parish_id' => $target->id]);
    $otherAct = Source::factory()->for($user->tree)->create(['parish_id' => $other->id]);

    $this->actingAs($user)->post(route('parishes.merge', $duplicate), ['target_id' => $target->id])
        ->assertRedirect(route('places.index', ['kind' => 'parafie', 'parish' => $target->id]))
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Scalono z parafią / urzędem „Luboml”']);

    expect(Parish::find($duplicate->id))->toBeNull();
    expect($movedAct->refresh()->parish_id)->toBe($target->id);
    expect($ownAct->refresh()->parish_id)->toBe($target->id);
    expect($otherAct->refresh()->parish_id)->toBe($other->id);
    expect($target->refresh()->name)->toBe('Luboml');
});

test('merging fills the empty seat of the target from the duplicate', function () {
    $user = User::factory()->create();
    $seat = Locality::factory()->for($user->tree)->create();
    $duplicate = Parish::factory()->for($user->tree)->create(['name' => 'Lubomla', 'locality_id' => $seat->id]);
    $target = Parish::factory()->for($user->tree)->create(['name' => 'Luboml', 'locality_id' => null]);

    $this->actingAs($user)->post(route('parishes.merge', $duplicate), ['target_id' => $target->id])->assertSessionHasNoErrors();

    expect($target->refresh()->locality_id)->toBe($seat->id);
});

test('merging keeps the seat of the target when it is set', function () {
    $user = User::factory()->create();
    $targetSeat = Locality::factory()->for($user->tree)->create();
    $duplicateSeat = Locality::factory()->for($user->tree)->create();
    $duplicate = Parish::factory()->for($user->tree)->create(['name' => 'Lubomla', 'locality_id' => $duplicateSeat->id]);
    $target = Parish::factory()->for($user->tree)->create(['name' => 'Luboml', 'locality_id' => $targetSeat->id]);

    $this->actingAs($user)->post(route('parishes.merge', $duplicate), ['target_id' => $target->id])->assertSessionHasNoErrors();

    expect($target->refresh()->locality_id)->toBe($targetSeat->id);
});

test('merging takes the religion of the duplicate when the target has none and no other parish has that name and religion', function () {
    $user = User::factory()->create();
    $duplicate = Parish::factory()->for($user->tree)->withReligion(Religion::RomanCatholic)->create(['name' => 'Luboml']);
    $target = Parish::factory()->for($user->tree)->create(['name' => 'Luboml']);

    $this->actingAs($user)->post(route('parishes.merge', $duplicate), ['target_id' => $target->id])->assertSessionHasNoErrors();

    expect($target->refresh()->religion)->toBe(Religion::RomanCatholic);
});

test('merging leaves the religion empty when a third parish already holds the resulting key', function () {
    $user = User::factory()->create();
    $seat = Locality::factory()->for($user->tree)->create();
    $duplicate = Parish::factory()->for($user->tree)->withReligion(Religion::RomanCatholic)->create();
    $target = Parish::factory()->seatedIn($seat)->create();
    Parish::factory()->seatedIn($seat)->withReligion(Religion::RomanCatholic)->create();

    $this->actingAs($user)->post(route('parishes.merge', $duplicate), ['target_id' => $target->id])
        ->assertSessionHasNoErrors();

    expect($target->refresh()->religion)->toBeNull();
    expect(Parish::find($duplicate->id))->toBeNull();
});

test('merging gives a target without a seat the seat, religion and dedication of the duplicate together', function () {
    $user = User::factory()->create();
    $seat = Locality::factory()->for($user->tree)->create();
    $duplicate = Parish::factory()->seatedIn($seat)->withReligion(Religion::GreekCatholic)->dedicatedTo('św. Jana')->create();
    $target = Parish::factory()->for($user->tree)->legacy('Luboml')->create();

    $this->actingAs($user)->post(route('parishes.merge', $duplicate), ['target_id' => $target->id])->assertSessionHasNoErrors();

    $target->refresh();
    expect($target->locality_id)->toBe($seat->id)
        ->and($target->religion)->toBe(Religion::GreekCatholic)
        ->and($target->dedication)->toBe('św. Jana')
        ->and($target->name)->toBe('Luboml');
});

test('merging gives a target without a former name the former name of the duplicate, even when the key is taken', function () {
    $user = User::factory()->create();
    $seat = Locality::factory()->for($user->tree)->create();
    $otherSeat = Locality::factory()->for($user->tree)->create();
    $duplicate = Parish::factory()->withReligion(Religion::RomanCatholic)->legacy('Lubomla', $otherSeat)->create();
    $target = Parish::factory()->seatedIn($seat)->create();
    Parish::factory()->seatedIn($seat)->withReligion(Religion::RomanCatholic)->create();

    $this->actingAs($user)->post(route('parishes.merge', $duplicate), ['target_id' => $target->id])->assertSessionHasNoErrors();

    $target->refresh();
    expect($target->name)->toBe('Lubomla')
        ->and($target->religion)->toBeNull();
});

test('merging keeps the former name of the target', function () {
    $user = User::factory()->create();
    $seat = Locality::factory()->for($user->tree)->create();
    $otherSeat = Locality::factory()->for($user->tree)->create();
    $duplicate = Parish::factory()->legacy('Lubomla', $otherSeat)->create();
    $target = Parish::factory()->legacy('Luboml', $seat)->create();

    $this->actingAs($user)->post(route('parishes.merge', $duplicate), ['target_id' => $target->id])->assertSessionHasNoErrors();

    expect($target->refresh()->name)->toBe('Luboml');
});

test('merging parishes of different religions is allowed and the target keeps its own', function () {
    $user = User::factory()->create();
    $duplicate = Parish::factory()->for($user->tree)->withReligion(Religion::GreekCatholic)->create(['name' => 'Luboml']);
    $target = Parish::factory()->for($user->tree)->withReligion(Religion::RomanCatholic)->create(['name' => 'Luboml']);

    $this->actingAs($user)->post(route('parishes.merge', $duplicate), ['target_id' => $target->id])->assertSessionHasNoErrors();

    expect($target->refresh()->religion)->toBe(Religion::RomanCatholic);
    expect(Parish::find($duplicate->id))->toBeNull();
});

test('merging refuses a target that is foreign, missing or the duplicate itself and changes nothing', function (string $kind) {
    $user = User::factory()->create();
    $duplicate = Parish::factory()->for($user->tree)->create(['name' => 'Luboml']);
    $foreign = Parish::factory()->for(User::factory()->create()->tree)->create();
    $targetId = match ($kind) {
        'foreign' => $foreign->id,
        'missing' => 999_999,
        'itself' => $duplicate->id,
    };

    $this->actingAs($user)->post(route('parishes.merge', $duplicate), ['target_id' => $targetId])
        ->assertSessionHasErrors('target_id');

    expect(Parish::find($duplicate->id))->not->toBeNull();
})->with(['foreign', 'missing', 'itself']);
