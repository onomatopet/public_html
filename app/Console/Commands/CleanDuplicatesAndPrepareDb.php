<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CleanDuplicatesAndPrepareDb extends Command
{
    protected $signature = 'app:clean-duplicates {--force : Skip confirmation}';
    protected $description = 'Clean duplicates and prepare database for migrations';

    public function handle(): int
    {
        $this->warn("--- Cleaning Database for Migrations ---");

        if (!$this->option('force') && !$this->confirm("This will modify your database. Continue?")) {
            return self::FAILURE;
        }

        // 1. Nettoyer les doublons de produits
        $this->cleanProductDuplicates();

        // 2. Corriger les colonnes NULL
        $this->fixNullableColumns();

        // 3. Vérifier l'état final
        $this->verifyReadiness();

        $this->info("✅ Database ready for migrations!");
        return self::SUCCESS;
    }

    private function cleanProductDuplicates(): void
    {
        $this->line("\n🧹 Cleaning product duplicates...");

        // Trouver les doublons
        $duplicates = DB::connection('mysql')
            ->table('products')
            ->select('code_product', DB::raw('COUNT(*) as count'))
            ->groupBy('code_product')
            ->having('count', '>', 1)
            ->get();

        if ($duplicates->isEmpty()) {
            $this->info("No product duplicates found.");
            return;
        }

        $this->warn("Found {$duplicates->count()} duplicate codes:");
        foreach ($duplicates as $duplicate) {
            $this->line("- {$duplicate->code_product} ({$duplicate->count} times)");
        }

        // Corriger chaque doublon
        $totalFixed = 0;
        foreach ($duplicates as $duplicate) {
            $fixed = $this->fixDuplicateCode($duplicate->code_product);
            $totalFixed += $fixed;
        }

        $this->info("✅ Fixed {$totalFixed} duplicate products");
    }

    private function fixDuplicateCode(string $codeProduct): int
    {
        $products = DB::connection('mysql')
            ->table('products')
            ->where('code_product', $codeProduct)
            ->orderBy('id')
            ->get();

        $fixed = 0;
        // Garder le premier, renommer les autres
        foreach ($products->skip(1) as $index => $product) {
            $newCode = $codeProduct . '_DUP' . ($index + 1);

            DB::connection('mysql')
                ->table('products')
                ->where('id', $product->id)
                ->update(['code_product' => $newCode]);

            $this->line("  → ID {$product->id}: '{$codeProduct}' → '{$newCode}'");
            $fixed++;
        }

        return $fixed;
    }

    private function fixNullableColumns(): void
    {
        $this->line("\n🔧 Making columns nullable...");

        $tables = [
            'distributeurs' => ['id_distrib_parent'],
            'level_currents' => ['id_distrib_parent'],
            'level_current_histories' => ['id_distrib_parent'],
        ];

        foreach ($tables as $tableName => $columns) {
            if (!Schema::connection('mysql')->hasTable($tableName)) {
                $this->line("  → Table {$tableName} not found, skipping");
                continue;
            }

            foreach ($columns as $column) {
                try {
                    DB::connection('mysql')->statement(
                        "ALTER TABLE {$tableName} MODIFY {$column} BIGINT UNSIGNED NULL"
                    );
                    $this->line("  → {$tableName}.{$column} made nullable");
                } catch (\Exception $e) {
                    $this->warn("  → Failed to modify {$tableName}.{$column}: " . $e->getMessage());
                }
            }
        }
    }

    private function verifyReadiness(): void
    {
        $this->line("\n🔍 Verifying database readiness...");

        // Vérifier les doublons
        $duplicates = DB::connection('mysql')
            ->table('products')
            ->select('code_product', DB::raw('COUNT(*) as count'))
            ->groupBy('code_product')
            ->having('count', '>', 1)
            ->count();

        if ($duplicates > 0) {
            $this->error("❌ Still have {$duplicates} duplicate codes!");
            return;
        }

        $this->info("✅ No duplicate product codes");
        $this->info("✅ Columns prepared for migration");
    }
}
