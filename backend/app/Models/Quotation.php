<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Quotation extends Model
{
    protected $table = 'quotations';

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id',
        'lead_id',
        'quote_type',
        'quote_number',
        'client_name',
        'client_email',
        'move_type',
        'from_location',
        'to_location',
        'move_date',
        'items',
        'subtotal',
        'tax',
        'total',
        'packages',
        'selected_package',
        'deposit_percent',
        'deposit_amount',
        'payment_option',
        'initial_deposit_paid',
        'notes',
        'status',
        'valid_until',
        'sent_at',
        'approved_at',
        'declined_at',
    ];

    protected $casts = [
        'items'                => 'array',
        'packages'             => 'array',
        'subtotal'             => 'decimal:2',
        'tax'                  => 'decimal:2',
        'total'                => 'decimal:2',
        'deposit_percent'      => 'decimal:2',
        'deposit_amount'       => 'decimal:2',
        'initial_deposit_paid' => 'boolean',
        'move_date'            => 'date',
        'valid_until'          => 'date',
        'sent_at'              => 'datetime',
        'approved_at'          => 'datetime',
        'declined_at'          => 'datetime',
        'created_at'           => 'datetime',
        'updated_at'           => 'datetime',
    ];

    /** The lead this quotation belongs to. */
    public function lead()
    {
        return $this->belongsTo(Lead::class, 'lead_id');
    }
}
