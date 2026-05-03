<?php

namespace Tests\Feature\Listeners;

use App\Models\User;
use Illuminate\Auth\Events\Login;
use Spatie\Activitylog\Models\Activity;

test('login event updates last_login_at quietly', function (): void {
    $user = User::factory()->create(['last_login_at' => null]);
    $before = Activity::query()->count();

    event(new Login('web', $user, false));

    $user->refresh();
    expect($user->last_login_at)->not->toBeNull();

    // saveQuietly() means LogsActivity should NOT fire — same row count.
    expect(Activity::query()->count())->toBe($before);
});
