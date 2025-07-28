<?php

return [
    /*
    |--------------------------------------------------------------------------
    | MLM Data Cleaning Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration pour le système de nettoyage des données MLM
    |
    */

    // Performance settings
    'performance' => [
        'chunk_size' => env('MLM_CLEANING_CHUNK_SIZE', 500),
        'memory_limit' => env('MLM_CLEANING_MEMORY_LIMIT', '2G'),
        'time_limit' => env('MLM_CLEANING_TIME_LIMIT', 3600), // 1 hour
        'sleep_between_chunks' => env('MLM_CLEANING_SLEEP', 0), // milliseconds
    ],

    // Snapshot settings
    'snapshot' => [
        'enabled' => env('MLM_CLEANING_SNAPSHOT_ENABLED', true),
        'storage_disk' => env('MLM_CLEANING_SNAPSHOT_DISK', 'local'),
        'path' => 'mlm-cleaning/snapshots',
        'retention_days' => env('MLM_CLEANING_SNAPSHOT_RETENTION', 30),
        'compression' => env('MLM_CLEANING_SNAPSHOT_COMPRESSION', true),
    ],

    // Grade advancement rules
    'grades' => [
        'rules' => [
            2 => [
                'conditions' => [
                    ['cumul_individuel' => 100]
                ]
            ],
            3 => [
                'conditions' => [
                    ['cumul_individuel' => 200]
                ]
            ],
            4 => [
                'conditions' => [
                    // Condition 1
                    ['cumul_individuel' => 1000],
                    // Condition 2
                    ['min_grade' => 3, 'children' => ['grade' => 3, 'count' => 2, 'feet' => 2], 'cumul_collectif' => 2200],
                    // Condition 3
                    ['min_grade' => 3, 'children' => ['grade' => 3, 'count' => 3, 'feet' => 3], 'cumul_collectif' => 1000],
                ]
            ],
            5 => [
                'conditions' => [
                    // Condition 1
                    ['min_grade' => 3, 'children' => ['grade' => 4, 'count' => 2, 'feet' => 2], 'cumul_collectif' => 7800],
                    // Condition 2
                    ['min_grade' => 3, 'children' => ['grade' => 4, 'count' => 3, 'feet' => 3], 'cumul_collectif' => 3800],
                    // Condition 3
                    ['min_grade' => 3, 'children' => [
                        ['grade' => 4, 'count' => 2, 'feet' => 2],
                        ['grade' => 3, 'count' => 4, 'feet' => 4]
                    ], 'cumul_collectif' => 3800],
                    // Condition 4
                    ['min_grade' => 3, 'children' => [
                        ['grade' => 4, 'count' => 1, 'feet' => 1],
                        ['grade' => 3, 'count' => 6, 'feet' => 6]
                    ], 'cumul_collectif' => 3800],
                ]
            ],
            6 => [
                'conditions' => [
                    // Condition 1
                    ['min_grade' => 3, 'children' => ['grade' => 5, 'count' => 2, 'feet' => 2], 'cumul_collectif' => 35000],
                    // Condition 2
                    ['min_grade' => 3, 'children' => ['grade' => 5, 'count' => 3, 'feet' => 3], 'cumul_collectif' => 16000],
                    // Condition 3
                    ['min_grade' => 3, 'children' => [
                        ['grade' => 5, 'count' => 2, 'feet' => 2],
                        ['grade' => 4, 'count' => 4, 'feet' => 4]
                    ], 'cumul_collectif' => 16000],
                    // Condition 4
                    ['min_grade' => 3, 'children' => [
                        ['grade' => 5, 'count' => 1, 'feet' => 1],
                        ['grade' => 4, 'count' => 6, 'feet' => 6]
                    ], 'cumul_collectif' => 16000],
                ]
            ],
            7 => [
                'conditions' => [
                    // Condition 1
                    ['min_grade' => 3, 'children' => ['grade' => 6, 'count' => 2, 'feet' => 2], 'cumul_collectif' => 145000],
                    // Condition 2
                    ['min_grade' => 3, 'children' => ['grade' => 6, 'count' => 3, 'feet' => 3], 'cumul_collectif' => 73000],
                    // Condition 3
                    ['min_grade' => 3, 'children' => [
                        ['grade' => 6, 'count' => 2, 'feet' => 2],
                        ['grade' => 5, 'count' => 4, 'feet' => 4]
                    ], 'cumul_collectif' => 73000],
                    // Condition 4
                    ['min_grade' => 3, 'children' => [
                        ['grade' => 6, 'count' => 1, 'feet' => 1],
                        ['grade' => 5, 'count' => 6, 'feet' => 6]
                    ], 'cumul_collectif' => 73000],
                ]
            ],
            8 => [
                'conditions' => [
                    // Condition 1
                    ['min_grade' => 3, 'children' => ['grade' => 7, 'count' => 2, 'feet' => 2], 'cumul_collectif' => 580000],
                    // Condition 2
                    ['min_grade' => 3, 'children' => ['grade' => 7, 'count' => 3, 'feet' => 3], 'cumul_collectif' => 280000],
                    // Condition 3
                    ['min_grade' => 3, 'children' => [
                        ['grade' => 7, 'count' => 2, 'feet' => 2],
                        ['grade' => 6, 'count' => 4, 'feet' => 4]
                    ], 'cumul_collectif' => 280000],
                    // Condition 4
                    ['min_grade' => 3, 'children' => [
                        ['grade' => 7, 'count' => 1, 'feet' => 1],
                        ['grade' => 6, 'count' => 6, 'feet' => 6]
                    ], 'cumul_collectif' => 280000],
                ]
            ],
            9 => [
                'conditions' => [
                    // Condition 1
                    ['min_grade' => 3, 'children' => ['grade' => 8, 'count' => 2, 'feet' => 2], 'cumul_collectif' => 780000],
                    // Condition 2
                    ['min_grade' => 3, 'children' => ['grade' => 8, 'count' => 3, 'feet' => 3], 'cumul_collectif' => 400000],
                    // Condition 3
                    ['min_grade' => 3, 'children' => [
                        ['grade' => 8, 'count' => 2, 'feet' => 2],
                        ['grade' => 7, 'count' => 4, 'feet' => 4]
                    ], 'cumul_collectif' => 400000],
                    // Condition 4
                    ['min_grade' => 3, 'children' => [
                        ['grade' => 8, 'count' => 1, 'feet' => 1],
                        ['grade' => 7, 'count' => 6, 'feet' => 6]
                    ], 'cumul_collectif' => 400000],
                ]
            ],
            10 => [
                'conditions' => [
                    ['min_grade' => 3, 'children' => ['grade' => 9, 'count' => 2, 'feet' => 2]]
                ]
            ],
            11 => [
                'conditions' => [
                    ['min_grade' => 3, 'children' => ['grade' => 9, 'count' => 3, 'feet' => 3]]
                ]
            ],
        ],
        'max_grade' => 11,
        'initial_grade' => 1,
        'allow_regression' => true, // Pour recalcul suite à changement de parrain
    ],

    // Anomaly detection settings
    'anomalies' => [
        'severity_levels' => [
            'hierarchy_loop' => 'critical',
            'orphan_parent' => 'high',
            'cumul_individual_negative' => 'high',
            'cumul_collective_less_than_individual' => 'high',
            'cumul_decrease' => 'low', // Car autorisé selon les règles
            'grade_regression' => 'medium',
            'grade_skip' => 'medium',
            'grade_conditions_not_met' => 'high',
            'missing_period' => 'low',
            'duplicate_period' => 'critical',
        ],
        'auto_fix' => [
            'cumul_individual_negative' => true,
            'cumul_collective_less_than_individual' => true,
            'orphan_parent' => true,
            'duplicate_period' => false,
            'hierarchy_loop' => false,
        ],
    ],

    // Period settings
    'periods' => [
        'format' => 'Y-m',
        'first_period' => '2024-02',
        'max_gap_months' => 12, // Max de mois entre deux périodes actives
    ],

    // Logging settings
    'logging' => [
        'detailed' => env('MLM_CLEANING_DETAILED_LOGS', true),
        'log_skipped' => env('MLM_CLEANING_LOG_SKIPPED', false),
        'export_format' => 'excel', // excel, csv, json
    ],

    // Report settings
    'reports' => [
        'formats' => ['excel', 'pdf', 'csv'],
        'include_charts' => true,
        'max_anomalies_in_preview' => 100,
    ],

    // Rollback settings
    'rollback' => [
        'enabled' => true,
        'max_days' => 30,
        'require_confirmation' => true,
    ],

    // Cache settings
    'cache' => [
        'enabled' => env('MLM_CLEANING_CACHE_ENABLED', true),
        'ttl' => env('MLM_CLEANING_CACHE_TTL', 3600), // 1 hour
        'prefix' => 'mlm_cleaning_',
    ],

    // Queue settings
    'queue' => [
        'enabled' => env('MLM_CLEANING_USE_QUEUE', false),
        'connection' => env('MLM_CLEANING_QUEUE_CONNECTION', 'database'),
        'queue_name' => env('MLM_CLEANING_QUEUE_NAME', 'mlm-cleaning'),
    ],
];
