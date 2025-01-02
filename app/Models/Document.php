<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Document extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'title',
        'document_number',
        'document_date',
        'description',
        'document_type_id',
        'created_by',
        'file_path',
        'status',
        'approved_at',
        'approved_by',
        'first_approver_id',
        'final_approver_id',
        'approval_notes',
        'signed_file_path',
        'pdf_generated_at'
    ];

    // Enum untuk status dokumen
    protected $casts = [
        'document_date' => 'date',
        'approved_at' => 'datetime',
        'status' => 'string'
    ];

    // Relasi
    public function documentType()
    {
        return $this->belongsTo(DocumentType::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    // public function firstApprover()
    // {
    //     return $this->belongsTo(User::class, 'first_approver_id');
    // }

    // public function finalApprover()
    // {
    //     return $this->belongsTo(User::class, 'final_approver_id');
    // }

    public function first_approver()
    {
        return $this->belongsTo(User::class, 'first_approver_id');
    }

    public function final_approver()
    {
        return $this->belongsTo(User::class, 'final_approver_id');
    }

    public function qrCodes()
    {
        return $this->hasMany(QrCode::class);
    }

    public function approvals()
    {
        return $this->hasMany(DocumentApproval::class);
    }
}
