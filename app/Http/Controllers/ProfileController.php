<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use App\Models\User;
use App\Services\CoupleMembershipService;
use App\Support\GoogleLogin;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ProfileController extends Controller
{
    /**
     * Display the user's profile form.
     */
    public function edit(Request $request): View
    {
        return view('profile.edit', [
            'user' => $request->user(),
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $changingEmail = $request->validated('email') !== $request->user()->email;
        if ($changingEmail && ! $request->user()->hasLocalPassword() && ! GoogleLogin::confirmed($request, 'email')) {
            throw ValidationException::withMessages(['email' => 'Confirme sua identidade com Google antes de alterar o e-mail.']);
        }
        $request->user()->fill($request->validated());

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();
        if ($changingEmail) {
            $request->session()->forget('google_confirmation');
        }

        return Redirect::route('profile.edit')->with('status', 'profile-updated');
    }

    /**
     * Delete the user's account.
     */
    public function destroy(Request $request, CoupleMembershipService $memberships): RedirectResponse
    {
        if ($request->user()->hasLocalPassword()) {
            $request->validateWithBag('userDeletion', ['password' => ['required', 'current_password']]);
        } elseif (! GoogleLogin::confirmed($request, 'delete')) {
            throw ValidationException::withMessages([
                'password' => 'Confirme sua identidade com Google antes de excluir a conta.',
            ])->errorBag('userDeletion');
        }

        $user = $request->user();

        try {
            DB::transaction(function () use ($user, $memberships) {
                if ($user->couple_id !== null) {
                    $memberships->leave($user);
                }
                $member = User::query()->lockForUpdate()->findOrFail($user->id);
                if ($member->subscriptions()->get()->contains(fn ($subscription) => $subscription->valid()
                    || ! in_array($subscription->stripe_status, ['canceled', 'incomplete_expired'], true))) {
                    throw ValidationException::withMessages([
                        'subscription' => 'Encerre a assinatura e aguarde o fim do acesso antes de excluir sua conta.',
                    ]);
                }
                DB::table('password_reset_tokens')->where('email', $member->email)->delete();
                DB::table('sessions')->where('user_id', $member->id)->delete();
                $member->delete();
            }, 3);
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors(), 'userDeletion');
        }

        // The cached authenticated model still has its old token. Avoid rotating
        // it back into the anonymized row during SessionGuard::logout().
        $user->setRememberToken(null);
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }
}
