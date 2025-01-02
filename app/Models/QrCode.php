<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class QrCode extends Model
{
    use HasFactory;

    protected $fillable = [
        'document_id',
        'qr_generation_id',
        'qr_code_path',
        'unique_hash',
        'verification_url',
        'additional_metadata',
        'generated_at',
        'is_verified',
        'verified_at'
    ];

    protected $casts = [
        'generated_at' => 'datetime',
        'verified_at' => 'datetime',
        'is_verified' => 'boolean',
        'additional_metadata' => 'json'
    ];

    // public function document()
    // {
    //     return $this->belongsTo(Document::class);
    // }
    public function document()
    {
        return $this->belongsTo(Document::class, 'document_id');
    }


    public function generation()
    {
        return $this->belongsTo(QrCodeGeneration::class, 'qr_generation_id');
    }

    public function verificationLogs()
    {
        return $this->hasMany(VerificationLog::class);
    }
}
