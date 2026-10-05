<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QuotationReminder extends Model
{
    protected $table = 'quotation_reminders';

    protected $fillable = [
        'lead_id',
        'quotation_id',
        'label',
        'scheduled_at',
        'status',
        'sent_at',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'sent_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function lead()
    {
        return $this->belongsTo(Lead::class, 'lead_id');
    }

    public function quotation()
    {
        return $this->belongsTo(Quotation::class, 'quotation_id');
    }
}
