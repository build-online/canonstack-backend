<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Traits\HasUuid;

class ApprovalHistory extends Model
{
    use HasFactory, HasUuid;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'uuid',
        'repository_id',
        'approver_id',
        'action',
        'previous_status',
        'new_status',
        'comment',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'action' => 'string',
        'previous_status' => 'string',
        'new_status' => 'string',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the repository that was reviewed.
     */
    public function repository(): BelongsTo
    {
        return $this->belongsTo(Repository::class);
    }

    /**
     * Get the approver who performed the action.
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approver_id');
    }

    /**
     * Scope to get approvals only.
     */
    public function scopeApprovals($query)
    {
        return $query->where('action', 'APPROVE');
    }

    /**
     * Scope to get rejections only.
     */
    public function scopeRejections($query)
    {
        return $query->where('action', 'REJECT');
    }

    /**
     * Scope to get history for a specific approver.
     */
    public function scopeByApprover($query, int $approverId)
    {
        return $query->where('approver_id', $approverId);
    }

    /**
     * Scope to get history for a specific repository.
     */
    public function scopeByRepository($query, int $repositoryId)
    {
        return $query->where('repository_id', $repositoryId);
    }
}
