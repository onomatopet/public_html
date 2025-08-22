<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class SystemConfig extends Model
{
    use HasFactory;

    protected $fillable = [
        'key',
        'value',
        'type',
        'description',
        'group',
        'is_public'
    ];

    protected $casts = [
        'is_public' => 'boolean'
    ];

    /**
     * Durée du cache en secondes
     */
    protected static $cacheDuration = 3600;

    /**
     * Récupère une valeur de configuration
     */
    public static function getValue($key, $default = null)
    {
        return Cache::remember("system_config.{$key}", static::$cacheDuration, function () use ($key, $default) {
            $config = static::where('key', $key)->first();

            if (!$config) {
                return $default;
            }

            return static::castValue($config->value, $config->type);
        });
    }

    /**
     * Définit une valeur de configuration
     */
    public static function setValue($key, $value, $type = null)
    {
        if ($type === null) {
            $type = static::detectType($value);
        }

        $config = static::updateOrCreate(
            ['key' => $key],
            [
                'value' => static::serializeValue($value, $type),
                'type' => $type
            ]
        );

        Cache::forget("system_config.{$key}");

        return $config;
    }

    /**
     * Récupère toutes les configurations d'un groupe
     */
    public static function getGroup($group)
    {
        return Cache::remember("system_config.group.{$group}", static::$cacheDuration, function () use ($group) {
            return static::where('group', $group)
                ->get()
                ->mapWithKeys(function ($config) {
                    return [$config->key => static::castValue($config->value, $config->type)];
                })
                ->toArray();
        });
    }

    /**
     * Récupère toutes les configurations publiques
     */
    public static function getPublic()
    {
        return Cache::remember("system_config.public", static::$cacheDuration, function () {
            return static::where('is_public', true)
                ->get()
                ->mapWithKeys(function ($config) {
                    return [$config->key => static::castValue($config->value, $config->type)];
                })
                ->toArray();
        });
    }

    /**
     * Vide le cache des configurations
     */
    public static function clearCache()
    {
        Cache::forget("system_config.*");
        Cache::tags(['system_config'])->flush();
    }

    /**
     * Cast une valeur selon son type
     */
    protected static function castValue($value, $type)
    {
        return match($type) {
            'integer' => (int) $value,
            'float' => (float) $value,
            'boolean' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            'array' => json_decode($value, true) ?? [],
            'json' => json_decode($value, true),
            default => $value
        };
    }

    /**
     * Sérialise une valeur selon son type
     */
    protected static function serializeValue($value, $type)
    {
        return match($type) {
            'boolean' => $value ? '1' : '0',
            'array', 'json' => json_encode($value),
            default => (string) $value
        };
    }

    /**
     * Détecte le type d'une valeur
     */
    protected static function detectType($value)
    {
        if (is_bool($value)) return 'boolean';
        if (is_int($value)) return 'integer';
        if (is_float($value)) return 'float';
        if (is_array($value)) return 'array';
        return 'string';
    }

    /**
     * Boot method
     */
    protected static function boot()
    {
        parent::boot();

        // Vider le cache lors de la modification
        static::saved(function ($model) {
            Cache::forget("system_config.{$model->key}");
            if ($model->group) {
                Cache::forget("system_config.group.{$model->group}");
            }
            Cache::forget("system_config.public");
        });

        static::deleted(function ($model) {
            Cache::forget("system_config.{$model->key}");
            if ($model->group) {
                Cache::forget("system_config.group.{$model->group}");
            }
            Cache::forget("system_config.public");
        });
    }
}
