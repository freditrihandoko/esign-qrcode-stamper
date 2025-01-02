<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DocumentType extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'description',
        'requires_approval',
        'default_first_approver_id',
        'default_final_approver_id'
    ];

    // Relasi dengan Dokumen
    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }

    public function defaultFirstApprover(): BelongsTo
    {
        return $this->belongsTo(User::class, 'default_first_approver_id');
    }

    public function defaultFinalApprover(): BelongsTo
    {
        return $this->belongsTo(User::class, 'default_final_approver_id');
    }
}
