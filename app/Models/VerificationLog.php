<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class VerificationLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'qr_code_id',
        'verified_by',
        'ip_address',
        'device_info',
        'browser_info',
        'is_successful'
    ];

    protected $casts = [
        'is_successful' => 'boolean'
    ];

    public function qrCode()
    {
        return $this->belongsTo(QrCode::class);
    }

    public function verifiedBy()
    {
        return $this->belongsTo(User::class, 'verified_by');
    }
}
