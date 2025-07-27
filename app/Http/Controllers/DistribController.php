<?php

namespace App\Http\Controllers;

use App\Models\LevelCurrentTest;
use Illuminate\Http\Request;

class DistribController extends Controller
{
    public function show($distributeurId)
    {
        // Chargement récursif de tous les descendants via childrenRecursive
        $distributeur = LevelCurrentTest::with('childrenRecursive')
            ->where('distributeur_id', $distributeurId)
            ->firstOrFail();

        return view('distributeurs.show', compact('distributeur'));
    }
}
