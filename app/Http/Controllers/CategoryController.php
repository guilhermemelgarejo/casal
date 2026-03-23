<?php

namespace App\Http\Controllers;

use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CategoryController extends Controller
{
    public function index()
    {
        $categories = Auth::user()->couple->categories;
        return view('categories.index', compact('categories'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|in:income,expense',
            'color' => 'nullable|string',
            'icon' => 'nullable|string',
        ]);

        Category::create([
            'couple_id' => Auth::user()->couple_id,
            'name' => $request->name,
            'type' => $request->type,
            'color' => $request->color,
            'icon' => $request->icon,
        ]);

        return back()->with('success', 'Categoria criada!');
    }

    public function update(Request $request, Category $category)
    {
        if ($category->couple_id !== Auth::user()->couple_id) {
            abort(403);
        }

        $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|in:income,expense',
            'color' => 'nullable|string',
            'icon' => 'nullable|string',
        ]);

        $category->update($request->only(['name', 'type', 'color', 'icon']));

        return back()->with('success', 'Categoria atualizada!');
    }

    public function destroy(Category $category)
    {
        if ($category->couple_id !== Auth::user()->couple_id) {
            abort(403);
        }

        $category->delete();
        return back()->with('success', 'Categoria excluída!');
    }
}
