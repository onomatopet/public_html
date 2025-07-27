<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\GradeCalculatorService;
use App\Models\LevelCurrentTest;

class GradeController extends Controller
{
    protected $gradeService;

    public function __construct(GradeCalculatorService $gradeService)
    {
        $this->gradeService = $gradeService;
    }

    /**
     * Met à jour les étoiles (grade) d'un distributeur pour une période donnée.
     */
    public function updateGrade(Request $request, $id)
    {
        $period = $request->input('period'); // peut être transmis en ?period=2025-02 ou via JSON

        $newGrade = $this->gradeService->calculateGrade($id, $period);

        if (is_null($newGrade)) {
            return response()->json([
                'message' => 'Distributeur non trouvé ou période invalide.',
                'distributeur_id' => $id,
                'period' => $period
            ], 404);
        }

        return response()->json([
            'distributeur_id' => $id,
            'new_etoiles' => $newGrade,
            'period' => $period
        ]);
    }
}
