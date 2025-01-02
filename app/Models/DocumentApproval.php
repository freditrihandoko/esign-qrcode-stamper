<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class DocumentApproval extends Model
{
    use HasFactory;

    protected $fillable = [
        'document_id',
        'approver_id',
        'approval_status',
        'notes',
        'approved_at'
    ];

    protected $casts = [
        'approved_at' => 'datetime',
        'approval_status' => 'string'
    ];

    public function document()
    {
        return $this->belongsTo(Document::class);
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approver_id');
    }
}
