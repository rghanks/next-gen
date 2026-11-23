<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class LoginController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): View|RedirectResponse
    {
        if (Auth::check()) {
            return redirect()->route('lab.billing.index');
        }

        return view('auth.login');
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'login' => ['required', 'string'],
            'password' => ['required', 'string'],
            'remember' => ['nullable', 'boolean'],
        ]);

        $loginInput = trim((string) $request->input('login'));
        $throttleKey = Str::transliterate(Str::lower($loginInput).'|'.$request->ip());

        // Check rate limiting (brute-force protection: max 5 attempts per minute)
        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);

            throw ValidationException::withMessages([
                'login' => trans('auth.throttle', [
                    'seconds' => $seconds,
                    'minutes' => ceil($seconds / 60),
                ]) ?: "Too many login attempts. Please try again in {$seconds} seconds.",
            ]);
        }

        // Search for user by either username or email
        $user = User::where('username', $loginInput)
            ->orWhere('email', $loginInput)
            ->first();

        // Verify credentials
        if (! $user || ! Hash::check($request->input('password'), $user->password)) {
            RateLimiter::hit($throttleKey, 60);

            throw ValidationException::withMessages([
                'login' => 'These credentials do not match our laboratory records.',
            ]);
        }

        // Verify that the user account is active/authorized
        if (! $user->isActive()) {
            RateLimiter::hit($throttleKey, 60);

            throw ValidationException::withMessages([
                'login' => 'Your account is deactivated. Please contact your laboratory administrator.',
            ]);
        }

        // Clear rate limiter upon successful authentication
        RateLimiter::clear($throttleKey);

        // Perform login with optional "Remember Me"
        Auth::login($user, $request->boolean('remember'));

        // Prevent session fixation attack
        $request->session()->regenerate();

        return redirect()->intended(route('lab.billing.index'))
            ->with('status', 'Welcome back, ' . ($user->name ?: $user->username) . '!');
    }

    /**
     * Destroy an authenticated session (Logout).
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')
            ->with('status', 'You have been logged out securely.');
    }

    /**
     * Update the authenticated user's password.
     */
    public function updatePassword(Request $request): RedirectResponse
    {
        $validator = Validator::make($request->all(), [
            'current_password' => ['required', 'string', 'current_password'],
            'new_password' => ['required', 'string', Password::min(6), 'confirmed', 'different:current_password'],
        ], [
            'current_password.required' => 'Please enter your current password.',
            'current_password.current_password' => 'The current password you entered is incorrect.',
            'new_password.required' => 'Please enter a new password.',
            'new_password.min' => 'The new password must be at least 6 characters.',
            'new_password.confirmed' => 'The new password confirmation does not match.',
            'new_password.different' => 'The new password must be different from your current password.',
        ]);

        if ($validator->fails()) {
            return back()
                ->withErrors($validator, 'passwordUpdate')
                ->withInput();
        }

        /** @var User $user */
        $user = Auth::user();

        $user->forceFill([
            'password' => Hash::make($request->input('new_password')),
        ])->save();

        return back()->with('password_status', 'Password updated successfully! Please keep your new credentials secure.');
    }
}
