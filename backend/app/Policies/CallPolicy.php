<?php

namespace App\Policies;

use App\Models\Call;
use App\Models\User;

class CallPolicy
{
    /**
     * Determine whether the user can view any calls.
     */
    public function viewAny(User $user): bool
    {
        return $this->hasAccess($user);
    }

    /**
     * Determine whether the user can view the call details and transcript.
     */
    public function view(User $user, Call $call): bool
    {
        return $this->hasAccess($user, $call);
    }

    /**
     * Determine whether the user can listen to / stream the call recording.
     */
    public function viewRecording(User $user, Call $call): bool
    {
        return $this->hasAccess($user, $call);
    }

    /**
     * Determine whether the user can create or upload a call recording.
     */
    public function create(User $user): bool
    {
        return $this->hasAccess($user);
    }

    /**
     * Determine whether the user can retry transcription on a failed call.
     */
    public function retryTranscription(User $user, Call $call): bool
    {
        return $this->hasAccess($user, $call);
    }

    /**
     * Determine whether the user can delete a call record.
     */
    public function delete(User $user, Call $call): bool
    {
        return $user->role === 'admin';
    }

    /**
     * Helper to verify user permissions.
     */
    protected function hasAccess(User $user, ?Call $call = null): bool
    {
        if ($user->role === 'admin') {
            return true;
        }

        // Staff users or users with contacts/leads permission
        if ($user->role === 'staff') {
            return true;
        }

        $permissions = is_array($user->permissions) ? $user->permissions : [];
        if (!empty($permissions['contacts']) || !empty($permissions['leads'])) {
            return true;
        }

        // The user is the agent on the call
        if ($call && (int) $call->user_id === (int) $user->id) {
            return true;
        }

        return false;
    }
}
