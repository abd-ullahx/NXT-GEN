<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Contact extends Model
{
    protected $table = 'contacts';

    protected $keyType = 'string';
    public $incrementing = false;

    const UPDATED_AT = null;

    protected $fillable = [
        'id',
        'name',
        'email',
        'phone',
        'type',
        'moves',
        'lifetime_value',
        'status',
    ];

    protected $casts = [
        'moves'          => 'integer',
        'lifetime_value' => 'decimal:2',
    ];

    public function calls(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Call::class, 'contact_id');
    }
}
