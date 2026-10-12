<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaskDependency extends Model
{
    public const TYPE_FS = 'FS';
    public const TYPE_SS = 'SS';
    public const TYPE_FF = 'FF';
    public const TYPE_SF = 'SF';

    public const TYPES = [self::TYPE_FS, self::TYPE_SS, self::TYPE_FF, self::TYPE_SF];

    public $timestamps = false;

    protected $fillable = ['task_id', 'predecessor_id', 'type', 'lag'];

    protected $casts = [
        'task_id' => 'integer',
        'predecessor_id' => 'integer',
        'lag' => 'integer',
    ];

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class, 'task_id');
    }

    public function predecessor(): BelongsTo
    {
        return $this->belongsTo(Task::class, 'predecessor_id');
    }
}
