<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class SpaceController extends Controller
{
    public function index()
    {
        $spaces = \App\Models\Space::with('owner')->get();
        return view('spaces.index', compact('spaces'));
    }

    public function create()
    {
        return view('spaces.create');
    }

    public function store(Request $request)
    {
        $request->validate(['name' => 'required|string|max:255']);

        $request->user()->ownedSpaces()->create([
            'name' => $request->name,
        ]);

        return redirect()->route('spaces.index');
    }

    public function show(\App\Models\Space $space)
    {
        $space->load('rooms.presences.user');
        return view('spaces.show', compact('space'));
    }
}
