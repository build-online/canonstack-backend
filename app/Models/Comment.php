<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Traits\HasUuid;

class Comment extends Model
{
    use HasFactory, HasUuid;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'uuid',
        'user_id',
        'repository_id',
        'text',
        'is_approver',
        'is_from_approval_process',
    ];

    /**
     * The attributes that should be cast to native types.
     */
    protected $casts = [
        'is_approver' => 'boolean',
        'is_from_approval_process' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the user who made the comment.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the repository that was commented on.
     */
    public function repository(): BelongsTo
    {
        return $this->belongsTo(Repository::class);
    }

    /**
     * Determine if the comment should be marked as made by approver.
     * An approver comment is when user role is 'APPROVER'.
     * All approvers can now approve any repository.
     */
    public static function isApproverComment(User $user, Repository $repository): bool
    {
        return $user->role === 'APPROVER';
    }
}
