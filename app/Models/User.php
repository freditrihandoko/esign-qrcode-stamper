<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class User extends Authenticatable
{
    use HasFactory, Notifiable, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $guarded = ['id'];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function languages(): BelongsToMany
    {
        return $this->belongsToMany(Language::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => 'string'
        ];
    }

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function createdDocuments()
    {
        return $this->hasMany(Document::class, 'created_by');
    }

    public function approvedDocuments()
    {
        return $this->hasMany(Document::class, 'approved_by');
    }

    public function firstApprovedDocuments()
    {
        return $this->hasMany(Document::class, 'first_approver_id');
    }

    public function finalApprovedDocuments()
    {
        return $this->hasMany(Document::class, 'final_approver_id');
    }

    public function documentApprovals()
    {
        return $this->hasMany(DocumentApproval::class, 'approver_id');
    }

    public function qrCodeGenerations()
    {
        return $this->hasMany(QrCodeGeneration::class, 'created_by');
    }

    public function verificationLogs()
    {
        return $this->hasMany(VerificationLog::class, 'verified_by');
    }
}
