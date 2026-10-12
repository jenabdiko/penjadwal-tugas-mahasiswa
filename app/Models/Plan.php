<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Plan extends Model
{
    public const PRIORITY_LOW = 'Rendah';
    public const PRIORITY_MEDIUM = 'Sedang';
    public const PRIORITY_HIGH = 'Tinggi';

    // Status tidak disimpan: selalu dihitung otomatis oleh StatusService.
    public const STATUS_TODO = 'Belum Dimulai';
    public const STATUS_DOING = 'Sedang Dikerjakan';
    public const STATUS_DONE = 'Selesai';
    public const STATUS_LATE = 'Terlambat';

    public const PRIORITIES = [self::PRIORITY_LOW, self::PRIORITY_MEDIUM, self::PRIORITY_HIGH];
    public const STATUSES = [self::STATUS_TODO, self::STATUS_DOING, self::STATUS_DONE, self::STATUS_LATE];

    protected $fillable = [
        'name',
        'description',
        'start_date',
        'deadline',
        'priority',
        'off_saturday',
        'off_sunday',
    ];

    protected $casts = [
        'start_date' => 'date',
        'deadline' => 'date',
        'off_saturday' => 'boolean',
        'off_sunday' => 'boolean',
    ];

    public function holidays(): HasMany
    {
        return $this->hasMany(Holiday::class);
    }

    public function members(): HasMany
    {
        return $this->hasMany(Member::class);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }
}
