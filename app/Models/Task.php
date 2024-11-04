<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Task extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = [
        'title',
        'description',
        'status',
        'progress',
        'deadline_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'deadline_at' => 'datetime',
        'progress' => 'integer',
    ];


    /**
     * Valid status values for the task.
     *
     * @var array<string>
     */
    public const STATUSES = [
        'pending',
        'in-progress',
        'completed'
    ];
} 