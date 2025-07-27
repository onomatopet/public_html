<?php

namespace App\Services;

use App\Models\LevelCurrentTest;

class GradeService
{
    public function updateEtoiles($distributorId, $period = null)
    {
        $distributor = LevelCurrentTest::with('children')
        ->where('distributeur_id', $distributorId)
        ->when($period, fn ($query) => $query->where('period', $period))
        ->first();

        if (!$distributor) return null;

        $currentGrade = $distributor->etoiles;
        $cumulIndividuel = $distributor->cumul_individuel;
        $cumulCollectif = $distributor->cumul_collectif;

        switch ($currentGrade) {
            case 1:
                if ($cumulIndividuel >= 100) $currentGrade = 2;
                break;
            case 2:
                if ($cumulIndividuel >= 200) $currentGrade = 3;
                break;
            case 3:
                if ($cumulIndividuel >= 1000 ||
                    ($this->hasChildrenInDifferentBranches($distributor, 3, 2) && $cumulCollectif >= 2200) ||
                    ($this->hasChildrenInDifferentBranches($distributor, 3, 3) && $cumulCollectif >= 1000)) {
                    $currentGrade = 4;
                }
                break;
            case 4:
                if ((in_array($currentGrade, [3,4]) && $this->hasChildrenInDifferentBranches($distributor, 4, 2) && $cumulCollectif >= 7800) ||
                    (in_array($currentGrade, [3,4]) && $this->hasChildrenInDifferentBranches($distributor, 4, 3) && $cumulCollectif >= 3800) ||
                    (in_array($currentGrade, [3,4]) && $this->hasChildrenInDifferentBranches($distributor, 4, 2) && $this->hasChildrenInDifferentBranches($distributor, 3, 4) && $cumulCollectif >= 3800) ||
                    (in_array($currentGrade, [3,4]) && $this->hasChildrenInDifferentBranches($distributor, 4, 1) && $this->hasChildrenInDifferentBranches($distributor, 3, 6) && $cumulCollectif >= 3800)) {
                    $currentGrade = 5;
                }
                break;
            case 5:
                if ((in_array($currentGrade, [3,4,5]) && $this->hasChildrenInDifferentBranches($distributor, 5, 2) && $cumulCollectif >= 35000) ||
                    (in_array($currentGrade, [3,4,5]) && $this->hasChildrenInDifferentBranches($distributor, 5, 3) && $cumulCollectif >= 16000) ||
                    (in_array($currentGrade, [3,4,5]) && $this->hasChildrenInDifferentBranches($distributor, 5, 2) && $this->hasChildrenInDifferentBranches($distributor, 4, 4) && $cumulCollectif >= 16000) ||
                    (in_array($currentGrade, [3,4,5]) && $this->hasChildrenInDifferentBranches($distributor, 5, 1) && $this->hasChildrenInDifferentBranches($distributor, 4, 6) && $cumulCollectif >= 16000)) {
                    $currentGrade = 6;
                }
                break;
            case 6:
                if ((in_array($currentGrade, [3,4,5,6]) && $this->hasChildrenInDifferentBranches($distributor, 6, 2) && $cumulCollectif >= 145000) ||
                    (in_array($currentGrade, [3,4,5,6]) && $this->hasChildrenInDifferentBranches($distributor, 6, 3) && $cumulCollectif >= 73000) ||
                    (in_array($currentGrade, [3,4,5,6]) && $this->hasChildrenInDifferentBranches($distributor, 6, 2) && $this->hasChildrenInDifferentBranches($distributor, 5, 4) && $cumulCollectif >= 73000) ||
                    (in_array($currentGrade, [3,4,5,6]) && $this->hasChildrenInDifferentBranches($distributor, 6, 1) && $this->hasChildrenInDifferentBranches($distributor, 5, 6) && $cumulCollectif >= 73000)) {
                    $currentGrade = 7;
                }
                break;
            case 7:
                if ((in_array($currentGrade, [3,4,5,6,7]) && $this->hasChildrenInDifferentBranches($distributor, 7, 2) && $cumulCollectif >= 580000) ||
                    (in_array($currentGrade, [3,4,5,6,7]) && $this->hasChildrenInDifferentBranches($distributor, 7, 3) && $cumulCollectif >= 280000) ||
                    (in_array($currentGrade, [3,4,5,6,7]) && $this->hasChildrenInDifferentBranches($distributor, 7, 2) && $this->hasChildrenInDifferentBranches($distributor, 6, 4) && $cumulCollectif >= 280000) ||
                    (in_array($currentGrade, [3,4,5,6,7]) && $this->hasChildrenInDifferentBranches($distributor, 7, 1) && $this->hasChildrenInDifferentBranches($distributor, 6, 6) && $cumulCollectif >= 280000)) {
                    $currentGrade = 8;
                }
                break;
            case 8:
                if ((in_array($currentGrade, [3,4,5,6,7,8]) && $this->hasChildrenInDifferentBranches($distributor, 8, 2) && $cumulCollectif >= 780000) ||
                    (in_array($currentGrade, [3,4,5,6,7,8]) && $this->hasChildrenInDifferentBranches($distributor, 8, 3) && $cumulCollectif >= 400000) ||
                    (in_array($currentGrade, [3,4,5,6,7,8]) && $this->hasChildrenInDifferentBranches($distributor, 8, 2) && $this->hasChildrenInDifferentBranches($distributor, 7, 4) && $cumulCollectif >= 400000) ||
                    (in_array($currentGrade, [3,4,5,6,7,8]) && $this->hasChildrenInDifferentBranches($distributor, 8, 1) && $this->hasChildrenInDifferentBranches($distributor, 7, 6) && $cumulCollectif >= 400000)) {
                    $currentGrade = 9;
                }
                break;
            case 9:
                if ($this->hasChildrenInDifferentBranches($distributor, 9, 2)) $currentGrade = 10;
                break;
            case 10:
                if ($this->hasChildrenInDifferentBranches($distributor, 9, 3)) $currentGrade = 11;
                break;
        }
        /*
        $distributor->etoiles = $currentGrade;
        $distributor->save();
        */
        return $currentGrade;
    }

    private function hasChildrenInDifferentBranches($distributor, $requiredGrade, $requiredBranches)
    {
        $branchesWithGrade = 0;
        foreach ($distributor->children as $childBranch) {
            if ($this->branchHasGrade($childBranch, $requiredGrade)) {
                $branchesWithGrade++;
            }
            if ($branchesWithGrade >= $requiredBranches) return true;
        }
        return false;
    }

    private function branchHasGrade($distributor, $requiredGrade)
    {
        if ($distributor->etoiles >= $requiredGrade) return true;

        $children = $distributor->children()->get(); // charge les enfants à la volée

        foreach ($children as $child) {
            if ($this->branchHasGrade($child, $requiredGrade)) return true;
        }

        return false;
    }
}
