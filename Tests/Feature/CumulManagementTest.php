<?php

namespace Tests\Feature;

use App\Models\Distributeur;
use App\Models\LevelCurrent;
use App\Services\CumulManagementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use \Tests\CreatesApplication;

class CumulManagementTest extends BaseTestCase
{
    use RefreshDatabase;

    private CumulManagementService $cumulService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cumulService = app(CumulManagementService::class);
    }

    public function test_deletion_transfers_individual_cumul_to_parent()
    {
        // Arrange
        $period = '2025-01';

        // Créer une hiérarchie : Grand-parent -> Parent -> Enfant
        $grandParent = Distributeur::factory()->create();
        $parent = Distributeur::factory()->create(['id_distrib_parent' => $grandParent->id]);
        $child = Distributeur::factory()->create(['id_distrib_parent' => $parent->id]);

        // Créer les level_currents
        LevelCurrent::create([
            'distributeur_id' => $grandParent->id,
            'period' => $period,
            'cumul_individuel' => 100,
            'cumul_total' => 600, // 100 + 200 + 300
            'cumul_collectif' => 1600, // Historique cumulé
            'etoiles' => 1,
            'rang' => 1
        ]);

        LevelCurrent::create([
            'distributeur_id' => $parent->id,
            'period' => $period,
            'cumul_individuel' => 200,
            'cumul_total' => 500, // 200 + 300
            'cumul_collectif' => 1200,
            'etoiles' => 1,
            'rang' => 1,
            'id_distrib_parent' => $grandParent->id
        ]);

        LevelCurrent::create([
            'distributeur_id' => $child->id,
            'period' => $period,
            'cumul_individuel' => 300,
            'cumul_total' => 300,
            'cumul_collectif' => 800,
            'etoiles' => 1,
            'rang' => 1,
            'id_distrib_parent' => $parent->id
        ]);

        // Act - Supprimer le parent
        $result = $this->cumulService->handleDistributeurDeletion($parent, $period);

        // Assert
        $this->assertTrue($result['success']);
        $this->assertEquals(200, $result['transferred_amount']);

        // Vérifier que le grand-parent a reçu le cumul individuel
        $grandParentLevel = LevelCurrent::where('distributeur_id', $grandParent->id)
                                        ->where('period', $period)
                                        ->first();

        $this->assertEquals(300, $grandParentLevel->cumul_individuel); // 100 + 200

        // Vérifier que l'enfant est maintenant rattaché au grand-parent
        $child->refresh();
        $this->assertEquals($grandParent->id, $child->id_distrib_parent);
    }

    public function test_parent_change_updates_cumuls_correctly()
    {
        // Arrange
        $period = '2025-01';

        // Créer deux branches : A -> B et C (isolé)
        $parentA = Distributeur::factory()->create();
        $childB = Distributeur::factory()->create(['id_distrib_parent' => $parentA->id]);
        $newParentC = Distributeur::factory()->create();

        // Level currents initiaux
        LevelCurrent::create([
            'distributeur_id' => $parentA->id,
            'period' => $period,
            'cumul_individuel' => 100,
            'cumul_total' => 300, // 100 + 200 de B
            'cumul_collectif' => 1000,
            'etoiles' => 1,
            'rang' => 1
        ]);

        LevelCurrent::create([
            'distributeur_id' => $childB->id,
            'period' => $period,
            'cumul_individuel' => 200,
            'cumul_total' => 200,
            'cumul_collectif' => 700,
            'etoiles' => 1,
            'rang' => 1,
            'id_distrib_parent' => $parentA->id
        ]);

        LevelCurrent::create([
            'distributeur_id' => $newParentC->id,
            'period' => $period,
            'cumul_individuel' => 50,
            'cumul_total' => 50,
            'cumul_collectif' => 400,
            'etoiles' => 1,
            'rang' => 1
        ]);

        // Act - Déplacer B sous C
        $result = $this->cumulService->recalculateAfterParentChange(
            $childB,
            $parentA->id,
            $newParentC->id,
            $period
        );

        // Assert
        $this->assertTrue($result['success']);
        $this->assertEquals(200, $result['amount_moved']);

        // Vérifier les nouveaux cumuls
        $parentALevel = LevelCurrent::where('distributeur_id', $parentA->id)
                                    ->where('period', $period)
                                    ->first();

        $newParentCLevel = LevelCurrent::where('distributeur_id', $newParentC->id)
                                        ->where('period', $period)
                                        ->first();

        // A perd 200 de cumul_total
        $this->assertEquals(100, $parentALevel->cumul_total);
        $this->assertEquals(800, $parentALevel->cumul_collectif); // 1000 - 200

        // C gagne 200 de cumul_total
        $this->assertEquals(250, $newParentCLevel->cumul_total); // 50 + 200
        $this->assertEquals(600, $newParentCLevel->cumul_collectif); // 400 + 200
    }

    public function test_individual_cumul_calculation()
    {
        // Arrange
        $period = '2025-01';
        $parent = Distributeur::factory()->create();
        $child1 = Distributeur::factory()->create(['id_distrib_parent' => $parent->id]);
        $child2 = Distributeur::factory()->create(['id_distrib_parent' => $parent->id]);

        // Parent avec cumul_total de 1000
        LevelCurrent::create([
            'distributeur_id' => $parent->id,
            'period' => $period,
            'cumul_individuel' => 0, // À recalculer
            'cumul_total' => 1000,
            'cumul_collectif' => 3000,
            'etoiles' => 1,
            'rang' => 1
        ]);

        // Enfant 1 avec cumul_total de 300
        LevelCurrent::create([
            'distributeur_id' => $child1->id,
            'period' => $period,
            'cumul_individuel' => 300,
            'cumul_total' => 300,
            'cumul_collectif' => 800,
            'etoiles' => 1,
            'rang' => 1,
            'id_distrib_parent' => $parent->id
        ]);

        // Enfant 2 avec cumul_total de 400
        LevelCurrent::create([
            'distributeur_id' => $child2->id,
            'period' => $period,
            'cumul_individuel' => 400,
            'cumul_total' => 400,
            'cumul_collectif' => 900,
            'etoiles' => 1,
            'rang' => 1,
            'id_distrib_parent' => $parent->id
        ]);

        // Act
        $individualCumul = $this->cumulService->recalculateIndividualCumul($parent->id, $period);

        // Assert
        $this->assertEquals(300, $individualCumul); // 1000 - 300 - 400

        $parentLevel = LevelCurrent::where('distributeur_id', $parent->id)
                                   ->where('period', $period)
                                   ->first();

        $this->assertEquals(300, $parentLevel->cumul_individuel);
    }
}
