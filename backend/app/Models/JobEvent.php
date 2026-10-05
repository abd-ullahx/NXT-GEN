<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JobEvent extends Model
{
    protected $table = 'job_events';

    protected $keyType = 'string';
    public $incrementing = false;

    const UPDATED_AT = null;

    protected $fillable = [
        'id',
        'title',
        'type',
        'status',
        'event_date',
        'event_time',
        'lead_id',
        'driver',
        'vehicle',
        'location',
        'notes',
        'client_job_status',
        'driver_job_status',
        'job_reschedule_reason',
        'job_proposed_date',
        'job_proposed_time',
        'job_schedule_token',
        'started_at',
        'completed_at',
        'selfie_url',
        'start_meter_url',
        'start_back_url',
        'start_odometer',
        'end_meter_url',
        'end_back_url',
        'end_odometer',
        'distance_km',
        'fuel_liters',
    ];

    protected $casts = [
        'event_date'        => 'date',
        'job_proposed_date' => 'date',
        'started_at'        => 'datetime',
        'completed_at'      => 'datetime',
        'created_at'        => 'datetime',
        'start_odometer'    => 'float',
        'end_odometer'      => 'float',
        'distance_km'       => 'float',
        'fuel_liters'       => 'float',
    ];

    /** The lead this event belongs to, if any. */
    public function lead()
    {
        return $this->belongsTo(Lead::class, 'lead_id');
    }
}
