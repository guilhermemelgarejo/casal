<?php

namespace App\Http\Controllers;

use App\Mail\ContactMessageMail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class ContactController extends Controller
{
    private const RECIPIENT_EMAIL = 'guilherme.melgarejo@gmail.com';

    public function show(Request $request)
    {
        $user = $request->user();

        return view('contact', [
            'user' => $user,
            'couple' => $user?->couple,
        ]);
    }

    public function send(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255'],
            'subject' => ['nullable', 'string', 'max:120'],
            'message' => ['required', 'string', 'min:10', 'max:5000'],
        ]);

        $user = $request->user();

        Mail::to(self::RECIPIENT_EMAIL)->send(new ContactMessageMail(
            name: $validated['name'],
            email: $validated['email'],
            contactSubject: $validated['subject'] ?? null,
            messageBody: $validated['message'],
            user: $user,
            couple: $user?->couple,
        ));

        return redirect()
            ->route('contact.show')
            ->with('success', 'Mensagem enviada com sucesso. Obrigado pelo contato!');
    }
}
