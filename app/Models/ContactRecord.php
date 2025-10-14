<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ContactRecord extends Model
{
    /** @use HasFactory<\Database\Factories\ContactRecordFactory> */
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'customer_id',
        'user_id',
        'contact_type',
        'call_outcome',
        'call_direction',
        'occurred_at',
        'notes',
        'meta',
    ];

    protected $casts = [
        'occurred_at' => 'datetime',
        'meta' => 'array',
    ];

    public function customer()  { return $this->belongsTo(Customer::class); }
    public function user()      { return $this->belongsTo(User::class); }

}
