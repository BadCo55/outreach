<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Customer extends Model
{
    /** @use HasFactory<\Database\Factories\CustomerFactory> */
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'legacy_id',
        'first_name',
        'last_name',
        'phone_1',
        'phone_2',
        'email_1',
        'email_2',
        'social_media_links',
        'is_realtor',
        'latest_inspection',
        'last_contact_at',
        'last_contact_type',
    ];

    protected $casts = [
        'social_media_links' => 'array',
        'is_realtor' => 'boolean',
        'latest_inspection' => 'array',
        'last_contact_at' => 'datetime',
    ];

    protected $appends = [
        'full_name'
    ];

    public function contactRecords()                { return $this->hasMany(ContactRecord::class); }
    public function getFullNameAttribute():string   { return trim(($this->first_name ?? '').' '.($this->last_name ?? '')); }
}
