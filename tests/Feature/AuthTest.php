<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;

beforeEach(function () {
    // Reset rate limiter
    RateLimiter::clear('test_auth_user|127.0.0.1');
    RateLimiter::clear('test_auth_user@hospital.local|127.0.0.1');
});

test('login page is accessible to guests', function () {
    Auth::logout();
    $response = $this->get(route('login'));

    $response->assertOk();
    $response->assertSee('Sign In');
    $response->assertSee('Username or Email');
    $response->assertSee('Password');
});

test('authenticated user is redirected away from login page', function () {
    $user = User::first();
    $response = $this->actingAs($user)->get(route('login'));

    $response->assertRedirect(route('lab.billing.index'));
});

test('unauthenticated guest is redirected to login from protected lab routes', function () {
    Auth::logout();
    $response = $this->get(route('lab.billing.index'));

    $response->assertRedirect(route('login'));
});

test('user can authenticate using username and password', function () {
    Auth::logout();

    $user = User::firstOrCreate(
        ['username' => 'test_auth_user'],
        [
            'name' => 'Auth Test User',
            'email' => 'test_auth_user@hospital.local',
            'password' => Hash::make('secret123'),
            'active' => 1,
        ]
    );
    $user->update([
        'password' => Hash::make('secret123'),
        'active' => 1,
    ]);

    $response = $this->post(route('login.attempt'), [
        'login' => 'test_auth_user',
        'password' => 'secret123',
    ]);

    $response->assertRedirect(route('lab.billing.index'));
    $this->assertAuthenticatedAs($user);
});

test('user can authenticate using email and password', function () {
    Auth::logout();

    $user = User::firstOrCreate(
        ['username' => 'test_email_user'],
        [
            'name' => 'Auth Email User',
            'email' => 'test_email_user@hospital.local',
            'password' => Hash::make('secret456'),
            'active' => 1,
        ]
    );
    $user->update([
        'password' => Hash::make('secret456'),
        'active' => 1,
    ]);

    $response = $this->post(route('login.attempt'), [
        'login' => 'test_email_user@hospital.local',
        'password' => 'secret456',
    ]);

    $response->assertRedirect(route('lab.billing.index'));
    $this->assertAuthenticatedAs($user);
});

test('login fails with invalid password', function () {
    Auth::logout();

    $user = User::firstOrCreate(
        ['username' => 'test_auth_user'],
        [
            'name' => 'Auth Test User',
            'email' => 'test_auth_user@hospital.local',
            'password' => Hash::make('secret123'),
            'active' => 1,
        ]
    );

    $response = $this->from(route('login'))->post(route('login.attempt'), [
        'login' => 'test_auth_user',
        'password' => 'wrongpassword',
    ]);

    $response->assertRedirect(route('login'));
    $response->assertSessionHasErrors(['login']);
    $this->assertGuest();
});

test('inactive or deactivated user cannot log in', function () {
    Auth::logout();

    $user = User::firstOrCreate(
        ['username' => 'deactivated_user'],
        [
            'name' => 'Deactivated User',
            'email' => 'deactivated@hospital.local',
            'password' => Hash::make('secret123'),
            'active' => 0,
        ]
    );
    $user->update([
        'password' => Hash::make('secret123'),
        'active' => 0,
    ]);

    $response = $this->from(route('login'))->post(route('login.attempt'), [
        'login' => 'deactivated_user',
        'password' => 'secret123',
    ]);

    $response->assertRedirect(route('login'));
    $response->assertSessionHasErrors(['login']);
    expect(session('errors')->first('login'))->toContain('deactivated');
    $this->assertGuest();
});

test('rate limiter throttles after multiple failed login attempts', function () {
    Auth::logout();

    for ($i = 0; $i < 5; $i++) {
        $this->post(route('login.attempt'), [
            'login' => 'throttled_user',
            'password' => 'wrong',
        ]);
    }

    $response = $this->post(route('login.attempt'), [
        'login' => 'throttled_user',
        'password' => 'wrong',
    ]);

    $response->assertSessionHasErrors(['login']);
    expect(session('errors')->first('login'))->toMatch('/Too many|seconds/');
});

test('authenticated user can securely log out', function () {
    $user = User::first();

    $response = $this->actingAs($user)->post(route('logout'));

    $response->assertRedirect(route('login'));
    $this->assertGuest();
});

test('authenticated user can update password with valid current password', function () {
    $user = User::firstOrCreate(
        ['username' => 'pwd_change_user'],
        [
            'name' => 'Password Change User',
            'email' => 'pwd_change@hospital.local',
            'password' => Hash::make('oldPassword123'),
            'active' => 1,
        ]
    );
    $user->update(['password' => Hash::make('oldPassword123')]);

    $response = $this->actingAs($user)->post(route('user.password.update'), [
        'current_password' => 'oldPassword123',
        'new_password' => 'newPassword456',
        'new_password_confirmation' => 'newPassword456',
    ]);

    $response->assertSessionHas('password_status');
    $user->refresh();
    expect(Hash::check('newPassword456', $user->password))->toBeTrue();
});

test('password update fails when current password is wrong', function () {
    $user = User::firstOrCreate(
        ['username' => 'pwd_change_user2'],
        [
            'name' => 'Password Change User 2',
            'email' => 'pwd_change2@hospital.local',
            'password' => Hash::make('correctPassword123'),
            'active' => 1,
        ]
    );
    $user->update(['password' => Hash::make('correctPassword123')]);

    $response = $this->actingAs($user)->post(route('user.password.update'), [
        'current_password' => 'wrongPassword',
        'new_password' => 'newPassword456',
        'new_password_confirmation' => 'newPassword456',
    ]);

    $response->assertSessionHasErrors(['current_password'], errorBag: 'passwordUpdate');
    expect(session('errors')->getBag('passwordUpdate')->first('current_password'))
        ->toBe('The current password you entered is incorrect.');
    $user->refresh();
    expect(Hash::check('correctPassword123', $user->password))->toBeTrue();
});

test('password update fails when confirmation does not match', function () {
    $user = User::firstOrCreate(
        ['username' => 'pwd_change_user3'],
        [
            'name' => 'Password Change User 3',
            'email' => 'pwd_change3@hospital.local',
            'password' => Hash::make('correctPassword123'),
            'active' => 1,
        ]
    );
    $user->update(['password' => Hash::make('correctPassword123')]);

    $response = $this->actingAs($user)->post(route('user.password.update'), [
        'current_password' => 'correctPassword123',
        'new_password' => 'newPassword456',
        'new_password_confirmation' => 'differentPassword999',
    ]);

    $response->assertSessionHasErrors(['new_password'], errorBag: 'passwordUpdate');
    expect(session('errors')->getBag('passwordUpdate')->first('new_password'))
        ->toBe('The new password confirmation does not match.');
});

test('password update fails when new password is too short', function () {
    $user = User::firstOrCreate(
        ['username' => 'pwd_change_user4'],
        [
            'name' => 'Password Change User 4',
            'email' => 'pwd_change4@hospital.local',
            'password' => Hash::make('correctPassword123'),
            'active' => 1,
        ]
    );
    $user->update(['password' => Hash::make('correctPassword123')]);

    $response = $this->actingAs($user)->post(route('user.password.update'), [
        'current_password' => 'correctPassword123',
        'new_password' => '12345',
        'new_password_confirmation' => '12345',
    ]);

    $response->assertSessionHasErrors(['new_password'], errorBag: 'passwordUpdate');
    expect(session('errors')->getBag('passwordUpdate')->first('new_password'))
        ->toBe('The new password must be at least 6 characters.');
});

test('password update fails when new password is same as current password', function () {
    $user = User::firstOrCreate(
        ['username' => 'pwd_change_user5'],
        [
            'name' => 'Password Change User 5',
            'email' => 'pwd_change5@hospital.local',
            'password' => Hash::make('correctPassword123'),
            'active' => 1,
        ]
    );
    $user->update(['password' => Hash::make('correctPassword123')]);

    $response = $this->actingAs($user)->post(route('user.password.update'), [
        'current_password' => 'correctPassword123',
        'new_password' => 'correctPassword123',
        'new_password_confirmation' => 'correctPassword123',
    ]);

    $response->assertSessionHasErrors(['new_password'], errorBag: 'passwordUpdate');
    expect(session('errors')->getBag('passwordUpdate')->first('new_password'))
        ->toBe('The new password must be different from your current password.');
});
