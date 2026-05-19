<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class InviteMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user      = $this->user();
        $workspace = $user?->currentWorkspace;

        return $workspace !== null && $workspace->owner_id === $user->id;
    }

    public function rules(): array
    {
        return [
            'email'      => 'required|email|max:255',
            'role'       => 'required|in:admin,member,viewer',
            'projects'   => 'sometimes|array',
            'projects.*' => 'integer',
        ];
    }
}
