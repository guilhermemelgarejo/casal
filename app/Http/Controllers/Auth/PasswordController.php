<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Support\GoogleLogin;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class PasswordController extends Controller
{
    /**
     * Update the user's password.
     */
    public function update(Request $request): RedirectResponse
    {
        $hasPassword = $request->user()->hasLocalPassword();
        if (! $hasPassword && ! GoogleLogin::confirmed($request, 'password')) {
            throw ValidationException::withMessages([
                'current_password' => 'Confirme sua identidade com Google antes de definir a senha, ou use a recuperação por e-mail.',
            ])->errorBag('updatePassword');
        }
        $validated = $request->validateWithBag('updatePassword', [
            'current_password' => $hasPassword ? ['required', 'current_password'] : ['nullable'],
            'password' => ['required', Password::defaults(), 'confirmed'],
        ]);

        $request->user()->update([
            'password' => $validated['password'],
        ]);
        $request->session()->forget('google_confirmation');

        return back()->with('status', 'password-updated');
    }
}
