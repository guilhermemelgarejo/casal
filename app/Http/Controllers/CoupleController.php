<?php

namespace App\Http\Controllers;

use App\Models\Couple;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;

class CoupleController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        $couple = $user->couple;

        return view('couple.index', compact('user', 'couple'));
    }

    public function create(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $couple = Couple::create([
            'name' => $request->name,
            'invite_code' => Str::random(10),
        ]);

        // Default categories
        $defaults = [
            ['name' => 'Alimentação', 'type' => 'expense', 'color' => '#ef4444'],
            ['name' => 'Moradia', 'type' => 'expense', 'color' => '#3b82f6'],
            ['name' => 'Transporte', 'type' => 'expense', 'color' => '#f59e0b'],
            ['name' => 'Lazer', 'type' => 'expense', 'color' => '#10b981'],
            ['name' => 'Salário', 'type' => 'income', 'color' => '#8b5cf6'],
        ];

        foreach ($defaults as $default) {
            $couple->categories()->create($default);
        }

        $user = Auth::user();
        $user->couple_id = $couple->id;
        $user->save();

        return redirect()->route('couple.index')->with('success', 'Casal criado com sucesso!');
    }

    public function join(Request $request)
    {
        $request->validate([
            'invite_code' => 'required|string',
        ]);

        $couple = Couple::where('invite_code', $request->invite_code)->first();

        if (!$couple) {
            return back()->withErrors(['invite_code' => 'Código de convite inválido.']);
        }

        if ($couple->users()->count() >= 2) {
            return back()->withErrors(['invite_code' => 'Este casal já possui dois membros.']);
        }

        $user = Auth::user();
        $user->couple_id = $couple->id;
        $user->save();

        return redirect()->route('couple.index')->with('success', 'Você entrou no casal!');
    }
}
