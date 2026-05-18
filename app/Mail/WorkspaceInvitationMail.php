<?php

namespace App\Mail;

use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceInvitation;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class WorkspaceInvitationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public WorkspaceInvitation $invitation,
        public Workspace $workspace,
        public User $inviter,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            to: [new Address($this->invitation->email)],
            subject: "{$this->inviter->name} invited you to {$this->workspace->name} on Taskline",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.workspace-invitation',
            with: [
                'inviterName'   => $this->inviter->name,
                'workspaceName' => $this->workspace->name,
                'role'          => $this->invitation->role,
                'acceptUrl'     => route('invitations.accept', ['token' => $this->invitation->token]),
                'expiresAt'     => $this->invitation->expires_at,
            ],
        );
    }
}
