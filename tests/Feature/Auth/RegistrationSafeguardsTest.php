<?php

use App\Models\Person;
use App\Models\Source;
use App\Models\Tree;
use App\Models\User;
use App\Notifications\NewUserRegistered;
use App\Notifications\QueuedVerifyEmail;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Notification;
use Laravel\Fortify\Features;

beforeEach(function () {
    $this->skipUnlessFortifyHas(Features::registration());
    Notification::fake();
});

/**
 * @return array<string, string>
 */
function signUp(string $email = 'new@example.com', string $confirmation = 'password'): array
{
    return [
        'name' => 'Nowy Użytkownik',
        'email' => $email,
        'password' => 'password',
        'password_confirmation' => $confirmation,
    ];
}

// A: e-mail verification

test('a new account is sent the verification e-mail through the queue', function () {
    $this->post(route('register.store'), signUp())->assertRedirect(route('tree.index', absolute: false));

    $user = User::whereEmail('new@example.com')->firstOrFail();

    expect($user->hasVerifiedEmail())->toBeFalse()
        ->and(new QueuedVerifyEmail)->toBeInstanceOf(ShouldQueue::class);
    Notification::assertSentTo($user, QueuedVerifyEmail::class);
});

test('an unverified account cannot open the tree', function () {
    $user = User::factory()->unverified()->create();

    $this->actingAs($user)->get(route('tree.index'))->assertRedirect(route('verification.notice'));
});

test('accounts created before verification was enforced are marked as verified', function () {
    $legacy = User::factory()->unverified()->create();
    $verifiedAt = now()->subMonth()->startOfSecond();
    $verified = User::factory()->create(['email_verified_at' => $verifiedAt]);

    (require database_path('migrations/2026_11_16_120000_mark_existing_users_as_verified.php'))->up();

    expect($legacy->fresh()->hasVerifiedEmail())->toBeTrue()
        ->and($verified->fresh()->email_verified_at->equalTo($verifiedAt))->toBeTrue();
});

// B: sign-up attempts per IP address

test('sign-up attempts from one IP address are limited, failed ones included', function () {
    config(['registration.attempts_per_hour' => 2]);

    $this->post(route('register.store'), signUp(confirmation: 'mismatch'))->assertSessionHasErrors('password');
    $this->post(route('register.store'), signUp(confirmation: 'mismatch'))->assertSessionHasErrors('password');

    $this->post(route('register.store'), signUp())->assertSessionHasErrors('email');

    expect(User::whereEmail('new@example.com')->exists())->toBeFalse();
    $this->assertGuest();
});

test('the limit counts each IP address separately', function () {
    config(['registration.attempts_per_hour' => 1]);

    $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.1'])
        ->post(route('register.store'), signUp(confirmation: 'mismatch'))
        ->assertSessionHasErrors('password');

    $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.2'])
        ->post(route('register.store'), signUp())
        ->assertSessionHasNoErrors();

    expect(User::whereEmail('new@example.com')->exists())->toBeTrue();
});

test('logging in does not count towards the sign-up limit', function () {
    config(['registration.attempts_per_hour' => 1]);
    $user = User::factory()->create();

    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'wrong']);
    $this->post(route('logout'));

    $this->post(route('register.store'), signUp())->assertSessionHasNoErrors();
});

// C: e-mail to the owner

test('the owner is e-mailed about every new account', function () {
    config(['registration.notification_mail' => 'owner@example.com']);

    $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.7'])->post(route('register.store'), signUp());

    Notification::assertSentOnDemand(
        NewUserRegistered::class,
        fn (NewUserRegistered $notification, array $channels, AnonymousNotifiable $notifiable): bool => $notifiable->routes['mail'] === 'owner@example.com'
            && $notification->email === 'new@example.com'
            && $notification->ip === '203.0.113.7',
    );
});

test('no owner e-mail is sent when no address is configured', function () {
    config(['registration.notification_mail' => null]);

    $this->post(route('register.store'), signUp());

    Notification::assertSentOnDemandTimes(NewUserRegistered::class, 0);
});

test('the owner e-mail names the account and is queued', function () {
    $mail = (new NewUserRegistered(42, 'Nowy Użytkownik', 'new@example.com', '203.0.113.7'))
        ->toMail(new AnonymousNotifiable);
    $text = implode("\n", $mail->introLines);

    expect($text)->toContain('Nowy Użytkownik')
        ->toContain('new@example.com')
        ->toContain('203.0.113.7')
        ->toContain('42')
        ->and(new NewUserRegistered(1, 'a', 'b', null))->toBeInstanceOf(ShouldQueue::class);
});

// D: removing abandoned sign-ups

test('model:prune deletes unverified accounts with an empty tree after the grace period', function () {
    config(['registration.unverified_days' => 7]);
    $stale = User::factory()->unverified()->create(['created_at' => now()->subDays(8)]);
    $treeId = $stale->tree->id;

    Artisan::call('model:prune', ['--model' => [User::class]]);

    expect(User::find($stale->id))->toBeNull()
        ->and(Tree::find($treeId))->toBeNull();
});

test('model:prune keeps recent, verified and non-empty accounts', function () {
    config(['registration.unverified_days' => 7]);
    $recent = User::factory()->unverified()->create(['created_at' => now()->subDays(2)]);
    $verified = User::factory()->create(['created_at' => now()->subYear()]);
    $withPerson = User::factory()->unverified()->create(['created_at' => now()->subYear()]);
    Person::factory()->for($withPerson->tree)->create();
    $withSource = User::factory()->unverified()->create(['created_at' => now()->subYear()]);
    Source::factory()->for($withSource->tree)->create();

    Artisan::call('model:prune', ['--model' => [User::class]]);

    foreach ([$recent, $verified, $withPerson, $withSource] as $user) {
        expect(User::find($user->id))->not->toBeNull();
    }
});

test('abandoned sign-ups are pruned daily at 05:15 Warsaw in production', function () {
    // withSchedule() hooks into Artisan::starting(), which the test lifecycle resets between tests.
    Artisan::all();

    $prune = collect($this->app->make(Schedule::class)->events())
        ->first(fn (Event $event): bool => str_contains((string) $event->command, 'model:prune'));

    expect($prune)->toBeInstanceOf(Event::class)
        ->and((string) $prune->command)->toContain('App\\Models\\User')
        ->and($prune->expression)->toBe('15 5 * * *')
        ->and((string) $prune->timezone)->toBe('Europe/Warsaw')
        ->and($prune->environments)->toBe(['production']);
});
