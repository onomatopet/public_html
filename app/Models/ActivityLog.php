<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

class ActivityLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'action',
        'description',
        'subject_type',
        'subject_id',
        'properties',
        'ip_address',
        'user_agent',
        'session_id'
    ];

    protected $casts = [
        'properties' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    /**
     * Relations
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Scopes
     */
    public function scopeByUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeByAction($query, $action)
    {
        return $query->where('action', $action);
    }

    public function scopeRecent($query, $days = 7)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    public function scopeToday($query)
    {
        return $query->whereDate('created_at', today());
    }

    /**
     * Enregistre une activité
     */
    public static function log($action, $description = null, $subject = null, $properties = [])
    {
        $log = new static([
            'user_id' => Auth::id(),
            'action' => $action,
            'description' => $description ?? static::generateDescription($action, $subject),
            'properties' => $properties,
            'ip_address' => Request::ip(),
            'user_agent' => Request::userAgent(),
            'session_id' => session()->getId()
        ]);

        if ($subject) {
            $log->subject_type = get_class($subject);
            $log->subject_id = $subject->getKey();
        }

        $log->save();

        return $log;
    }

    /**
     * Enregistre une activité de création
     */
    public static function logCreated($model, $description = null)
    {
        $modelName = class_basename($model);
        $description = $description ?? "Création de {$modelName} #{$model->getKey()}";

        return static::log('create', $description, $model, [
            'attributes' => $model->getAttributes()
        ]);
    }

    /**
     * Enregistre une activité de modification
     */
    public static function logUpdated($model, $changes = [], $description = null)
    {
        $modelName = class_basename($model);
        $description = $description ?? "Modification de {$modelName} #{$model->getKey()}";

        return static::log('update', $description, $model, [
            'old' => $changes['old'] ?? [],
            'new' => $changes['new'] ?? [],
            'changes' => array_keys($changes['old'] ?? [])
        ]);
    }

    /**
     * Enregistre une activité de suppression
     */
    public static function logDeleted($model, $description = null)
    {
        $modelName = class_basename($model);
        $description = $description ?? "Suppression de {$modelName} #{$model->getKey()}";

        return static::log('delete', $description, null, [
            'model_type' => get_class($model),
            'model_id' => $model->getKey(),
            'attributes' => $model->getAttributes()
        ]);
    }

    /**
     * Enregistre une activité de connexion
     */
    public static function logLogin($user = null)
    {
        $user = $user ?? Auth::user();

        return static::log('login', "Connexion de l'utilisateur {$user->name}", $user, [
            'login_at' => now()->toDateTimeString()
        ]);
    }

    /**
     * Enregistre une activité de déconnexion
     */
    public static function logLogout($user = null)
    {
        $user = $user ?? Auth::user();

        return static::log('logout', "Déconnexion de l'utilisateur {$user->name}", $user, [
            'logout_at' => now()->toDateTimeString()
        ]);
    }

    /**
     * Enregistre une tentative de connexion échouée
     */
    public static function logFailedLogin($credentials)
    {
        return static::log('login_failed', "Tentative de connexion échouée", null, [
            'email' => $credentials['email'] ?? null,
            'attempted_at' => now()->toDateTimeString()
        ]);
    }

    /**
     * Enregistre une activité d'export
     */
    public static function logExport($type, $filters = [])
    {
        return static::log('export', "Export de données: {$type}", null, [
            'export_type' => $type,
            'filters' => $filters,
            'exported_at' => now()->toDateTimeString()
        ]);
    }

    /**
     * Enregistre une activité d'import
     */
    public static function logImport($type, $filename, $count = 0)
    {
        return static::log('import', "Import de données: {$type}", null, [
            'import_type' => $type,
            'filename' => $filename,
            'records_count' => $count,
            'imported_at' => now()->toDateTimeString()
        ]);
    }

    /**
     * Génère une description automatique
     */
    protected static function generateDescription($action, $subject = null)
    {
        if (!$subject) {
            return ucfirst($action);
        }

        $modelName = class_basename($subject);
        $id = $subject->getKey();

        return match($action) {
            'create' => "Création de {$modelName} #{$id}",
            'update' => "Modification de {$modelName} #{$id}",
            'delete' => "Suppression de {$modelName} #{$id}",
            'view' => "Consultation de {$modelName} #{$id}",
            default => "{$action} sur {$modelName} #{$id}"
        };
    }

    /**
     * Accesseurs
     */
    public function getFormattedCreatedAtAttribute()
    {
        return $this->created_at->format('d/m/Y H:i:s');
    }

    public function getFormattedActionAttribute()
    {
        $actions = [
            'create' => 'Création',
            'update' => 'Modification',
            'delete' => 'Suppression',
            'login' => 'Connexion',
            'logout' => 'Déconnexion',
            'login_failed' => 'Connexion échouée',
            'export' => 'Export',
            'import' => 'Import',
            'view' => 'Consultation'
        ];

        return $actions[$this->action] ?? ucfirst($this->action);
    }

    /**
     * Nettoie les anciens logs
     */
    public static function cleanup($days = 90)
    {
        return static::where('created_at', '<', now()->subDays($days))->delete();
    }
}
