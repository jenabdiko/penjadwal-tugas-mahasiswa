<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Holiday extends Model
{
    public $timestamps = false;

    protected $fillable = ['plan_id', 'date', 'name'];

    protected $casts = [
        'plan_id' => 'integer',
        'date' => 'date',
    ];

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }
}
