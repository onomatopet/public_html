<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BonusThreshold extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'bonus_thresholds';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'grade',
        'minimum_pv',
        'description',
        'is_active'
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'grade' => 'integer',
        'minimum_pv' => 'integer', // Changé de decimal à integer
        'is_active' => 'boolean',
    ];

    /**
     * Scope for active thresholds
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Get threshold for a specific grade
     */
    public static function forGrade($grade)
    {
        return static::where('grade', $grade)
            ->where('is_active', true)
            ->first();
    }

    /**
     * Get the grade name/description
     */
    public function getGradeNameAttribute()
    {
        return $this->description ?? "Distributeur {$this->grade} étoile" . ($this->grade > 1 ? 's' : '');
    }
}
