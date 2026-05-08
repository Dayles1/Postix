<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    use HasFactory;

    protected $table = 'audit_logs';

    /**
     * Mass assignment uchun ruxsat berilgan maydonlar
     */
    protected $fillable = [
        'type',
        'action',
        'description',
        'subject_type',
        'subject_id',
        'causer_type',
        'causer_id',
        'changes',
    ];

    /**
     * JSON maydonlar
     */
    protected $casts = [
        'changes' => 'array',
    ];

    /**
     * Subject (tegishli model) bilan polymorphic relation
     */
    public function subject()
    {
        return $this->morphTo();
    }

    /**
     * Causer (kim amalga oshirgan) bilan polymorphic relation
     */
    public function causer()
    {
        return $this->morphTo();
    }
}
