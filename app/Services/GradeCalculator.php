<?php
namespace App\Services;

class GradeCalculator
{
    public function calculatePotentialGrade(
        int $currentEtoiles,
        float $cumulIndividuel,
        float $cumulCollectif,
        int|string $matricule,
        EternalHelperLegacyMatriculeDB $branchQualifier
    ): int {
        // Grades 1-3 : basés uniquement sur le cumul individuel
        if ($currentEtoiles < 2 && $cumulIndividuel >= 100) {
            return 2;
        }
        if ($currentEtoiles < 3 && $cumulIndividuel >= 200) {
            return 3;
        }

        // Grade 4 : nécessite d'avoir le grade 3 minimum
        if ($currentEtoiles >= 3 && $currentEtoiles < 4) {
            // Option 1 : cumul individuel de 1000
            if ($cumulIndividuel >= 1000) {
                return 4;
            }
            // Options 2 et 3 : basées sur les branches qualifiées
            if ($this->canReachGrade(4, $cumulCollectif, $matricule, $branchQualifier)) {
                return 4;
            }
        }

        // Grades 5-9 : nécessitent d'avoir au moins le grade 3
        if ($currentEtoiles >= 3) {
            // Vérifier dans l'ordre croissant des grades
            for ($targetGrade = $currentEtoiles + 1; $targetGrade <= 9; $targetGrade++) {
                if ($this->canReachGrade($targetGrade, $cumulCollectif, $matricule, $branchQualifier)) {
                    // Ne pas sauter de grades, retourner le prochain grade atteignable
                    return $targetGrade;
                }
            }
        }

        // Grades 10-11 : règles spéciales basées sur les branches de grade 9
        if ($currentEtoiles >= 9) {
            $pass1 = $branchQualifier->countQualifiedBranches($matricule, 9);

            if ($currentEtoiles == 9) {
                if ($pass1 >= 3) return 11;  // Peut sauter directement au grade 11
                if ($pass1 >= 2) return 10;
                return 9;
            } elseif ($currentEtoiles == 10) {
                if ($pass1 >= 3) return 11;
                return 10;
            }
        }

        return $currentEtoiles;
    }

    private function canReachGrade(int $targetGrade, float $cumulCollectif, int|string $matricule, EternalHelperLegacyMatriculeDB $branchQualifier): bool
    {
        $rules = [
            4 => ['standard_cumul' => 2200,   'strong_cumul' => 1000],
            5 => ['standard_cumul' => 7800,   'strong_cumul' => 3800],
            6 => ['standard_cumul' => 35000,  'strong_cumul' => 16000],
            7 => ['standard_cumul' => 145000, 'strong_cumul' => 73000],
            8 => ['standard_cumul' => 580000, 'strong_cumul' => 280000],
            9 => ['standard_cumul' => 780000, 'strong_cumul' => 400000],
        ];

        if (!isset($rules[$targetGrade])) return false;
        $rule = $rules[$targetGrade];

        // Compter les branches qualifiées
        $pass1 = $branchQualifier->countQualifiedBranches($matricule, $targetGrade - 1);

        // Option 1 : Standard - 2 branches du grade N-1 + cumul standard
        if ($cumulCollectif >= $rule['standard_cumul'] && $pass1 >= 2) {
            return true;
        }

        // Options 2, 3, 4 : Strong - cumul réduit avec différentes combinaisons de branches
        if ($cumulCollectif >= $rule['strong_cumul']) {
            // Option 2 : 3+ branches du grade N-1
            if ($pass1 >= 3) {
                return true;
            }

            // Options 3 et 4 : combinaisons avec branches du grade N-2
            if ($targetGrade > 4) {
                $pass2 = $branchQualifier->countQualifiedBranches($matricule, $targetGrade - 2);

                // Option 3 : 2 branches N-1 + 4 branches N-2
                if ($pass1 >= 2 && $pass2 >= 4) {
                    return true;
                }

                // Option 4 : 1 branche N-1 + 6 branches N-2
                if ($pass1 >= 1 && $pass2 >= 6) {
                    return true;
                }
            }
        }

        return false;
    }
}
