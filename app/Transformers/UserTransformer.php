<?php

namespace App\Transformers;

use App\Models\User;
use League\Fractal\TransformerAbstract;

class UserTransformer extends TransformerAbstract
{
    protected array $availableIncludes = [
        'religiousMovement'
    ];

    /**
     * A Fractal transformer.
     *
     * @param User $user
     * @return array
     */
    public function transform(User $user): array
    {
        return [
            'uuid' => $user->uuid,
            'name' => $user->name,
            'username' => $user->username,
            'email' => $user->email,
            'role' => $user->role,
        ];
    }

    /**
     * Include religious movement.
     */
    public function includeReligiousMovement(User $user)
    {
        if ($user->religiousMovement) {
            return $this->item($user->religiousMovement, new ReligiousMovementTransformer());
        }
        return $this->null();
    }
}
