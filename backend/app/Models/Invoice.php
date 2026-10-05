<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
    protected $table = 'invoices';

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id',
        'lead_id',
        'quotation_id',
        'invoice_number',
        'client_name',
        'client_email',
        'service_title',
        'invoice_type',
        'status',
        'payment_method',
        'issue_date',
        'due_date',
        'items',
        'subtotal',
        'tax',
        'total',
        'paid_amount',
        'balance_due',
        'payment_reference',
        'paid_at',
    ];

    protected $casts = [
        'items'       => 'array',
        'subtotal'    => 'decimal:2',
        'tax'         => 'decimal:2',
        'total'       => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'balance_due' => 'decimal:2',
        'issue_date'  => 'date',
        'due_date'    => 'date',
        'paid_at'     => 'datetime',
        'created_at'  => 'datetime',
        'updated_at'  => 'datetime',
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
