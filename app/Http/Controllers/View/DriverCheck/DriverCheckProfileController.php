<?php

declare(strict_types=1);

namespace App\Http\Controllers\View\DriverCheck;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Throwable;

/**
 * The driver-check profile: the signed-in person's own account only.
 *
 * Apart from the postix profile (ProfileController), which manages other
 * users, roles, bans and limits - none of that belongs here.
 */
final class DriverCheckProfileController extends Controller
{
    public function show(Request $request)
    {
        $user = $request->user()->load(['avatar', 'role']);

        return view('pages.driver-check.profile', compact('user'));
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'avatar' => ['nullable', 'image', 'max:2048'],
            'remove_avatar' => ['nullable', 'boolean'],
        ]);

        $user->name = $data['name'];
        $user->email = $data['email'] ?? $user->email;
        $user->save();

        if ($request->hasFile('avatar')) {
            $path = $request->file('avatar')->store('avatars', 'public');

            $this->dropAvatarFile($user);
            $user->avatar()->updateOrCreate([], ['path' => $path]);
        } elseif ($request->boolean('remove_avatar')) {
            $this->dropAvatarFile($user);
            $user->avatar()->delete();
        }

        Cache::forget('header_html_user_' . $user->id . '_' . app()->getLocale());

        return redirect()
            ->route('driver-check.profile')
            ->with('success', __('driver_check.profile.saved'));
    }

    public function password(Request $request): RedirectResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:6', 'confirmed'],
        ]);

        if (! Hash::check($data['current_password'], (string) $user->password)) {
            return redirect()
                ->route('driver-check.profile')
                ->withErrors(['current_password' => __('driver_check.profile.password_wrong')], 'password');
        }

        $user->password = Hash::make($data['password']);
        $user->save();

        return redirect()
            ->route('driver-check.profile')
            ->with('success', __('driver_check.profile.password_saved'));
    }

    private function dropAvatarFile($user): void
    {
        try {
            $old = $user->avatar;

            if ($old && $old->path) {
                Storage::disk('public')->delete($old->path);
            }
        } catch (Throwable $e) {
            // the file may already be gone; the row is replaced next
        }
    }
}
