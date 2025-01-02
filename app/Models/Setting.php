<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $fillable = [
        'website_name',
        'email',
        'show_document_preview',
        'max_document_size'
    ];

    protected $casts = [
        'show_document_preview' => 'boolean',
        'max_document_size' => 'integer',
    ];
}
