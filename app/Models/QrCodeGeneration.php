<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class QrCodeGeneration extends Model
{
    use HasFactory;

    protected $fillable = [
        'created_by',
        'qr_generation_code',
        'generation_details'
    ];

    protected $casts = [
        'generation_details' => 'json'
    ];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function qrCodes()
    {
        return $this->hasMany(QrCode::class, 'qr_generation_id');
    }

    public function isUsed()
    {
        return $this->qrCodes()->exists();
    }
}
