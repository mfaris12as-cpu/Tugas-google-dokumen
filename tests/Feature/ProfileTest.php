<?php

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

test('authenticated user can view profile edit page', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('profile.edit'));

    $response->assertSuccessful();
});

test('authenticated user can update profile name and password', function () {
    $user = User::factory()->create([
        'name' => 'Old Name',
    ]);

    $response = $this->actingAs($user)->post(route('profile.update'), [
        'name' => 'New Name',
        'password' => 'newpassword123',
        'password_confirmation' => 'newpassword123',
    ]);

    $response->assertRedirect(route('home'));
    $user->refresh();

    expect($user->name)->toBe('New Name');
    expect(Hash::check('newpassword123', $user->password))->toBeTrue();
});

test('authenticated user can upload a custom avatar', function () {
    Storage::fake('public');
    $user = User::factory()->create();
    $file = UploadedFile::fake()->image('avatar.jpg');

    $response = $this->actingAs($user)->post(route('profile.update'), [
        'name' => 'Test User',
        'avatar' => $file,
    ]);

    $response->assertRedirect(route('home'));
    $user->refresh();

    expect($user->avatar)->not->toBeNull();
    Storage::disk('public')->assertExists($user->avatar);
});

test('default avatar fallback matches expected stock animal', function () {
    // Override ID sequence using factory
    $user = User::factory()->create(['id' => 1]); // 1 % 5 = 1 => 'fox'
    expect($user->avatar_url)->toContain('images/avatars/fox.svg');

    $user2 = User::factory()->create(['id' => 2]); // 2 % 5 = 2 => 'koala'
    expect($user2->avatar_url)->toContain('images/avatars/koala.svg');
});

test('guest user activity touches updated_at timestamp', function () {
    $guest = User::factory()->create([
        'email' => 'guest_abc@collabify.local',
        'updated_at' => now()->subMinutes(10),
    ]);

    $this->actingAs($guest)->get(route('home'));

    $guest->refresh();
    expect($guest->updated_at->gt(now()->subMinutes(1)))->toBeTrue();
});

test('expired guest users are deleted during request cycle', function () {
    // Create an expired guest
    $expiredGuest = User::factory()->create([
        'email' => 'guest_expired@collabify.local',
        'updated_at' => now()->subHours(25),
    ]);

    // Create an active guest
    $activeGuest = User::factory()->create([
        'email' => 'guest_active@collabify.local',
        'updated_at' => now(),
    ]);

    // Force run the cleanup by simulating requests until it hits the 5% chance
    for ($i = 0; $i < 100; $i++) {
        $this->actingAs($activeGuest)->get(route('home'));
        if (! User::where('id', $expiredGuest->id)->exists()) {
            break;
        }
    }

    expect(User::where('id', $expiredGuest->id)->exists())->toBeFalse();
    expect(User::where('id', $activeGuest->id)->exists())->toBeTrue();
});
