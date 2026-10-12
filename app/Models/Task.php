<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Task extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'plan_id',
        'parent_id',
        'name',
        'duration',
        'earliest_start',
        'priority',
        'progress',
        'sort_order',
    ];

    protected $casts = [
        'plan_id' => 'integer',
        'parent_id' => 'integer',
        'duration' => 'integer',
        'earliest_start' => 'date',
        'progress' => 'integer',
        'sort_order' => 'integer',
    ];

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order');
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(Member::class, 'task_member')->orderBy('name');
    }

    /** Dependency di mana task ini menjadi task yang bergantung. */
    public function dependencies(): HasMany
    {
        return $this->hasMany(TaskDependency::class, 'task_id');
    }

    /** Dependency di mana task ini menjadi predecessor. */
    public function predecessorDependencies(): HasMany
    {
        return $this->hasMany(TaskDependency::class, 'predecessor_id');
    }

    public function predecessors(): BelongsToMany
    {
        return $this->belongsToMany(
            self::class,
            'task_dependencies',
            'task_id',
            'predecessor_id'
        )->withPivot(['type', 'lag']);
    }

    public function successors(): BelongsToMany
    {
        return $this->belongsToMany(
            self::class,
            'task_dependencies',
            'predecessor_id',
            'task_id'
        )->withPivot(['type', 'lag']);
    }

    /** Batas progress: 0 sampai 100. */
    public static function clampProgress(int $progress): int
    {
        return max(0, min(100, $progress));
    }
}
