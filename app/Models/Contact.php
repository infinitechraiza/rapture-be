<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Contact extends Model
{
    protected $fillable = [
        'full_name',
        'email',
        'phone',
        'subject',
        'message',
        'status',
        'admin_reply',
        'replied_by',
        'replied_at',
    ];

    protected $casts = [
        'replied_at' => 'datetime',
    ];

    public function repliedByAdmin()
    {
        return $this->belongsTo(User::class, 'replied_by');
    }


    // Custom timestamps
    const CREATED_AT = 'date_created';
    const UPDATED_AT = 'date_updated';
}
