<?php

namespace App\Mail;

use App\Models\Comment;
use App\Models\Project;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

class ProjectChatMessageMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Project $project,
        public Comment $comment,
        public string $audience = 'team',
        public ?string $portal = null,
    ) {
    }

    public function envelope(): Envelope
    {
        $subjectPrefix = $this->audience === 'customer'
            ? 'Customer update'
            : 'Team update';

        return new Envelope(
            subject: $subjectPrefix . ': ' . Str::limit($this->project->name, 70),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.project-chat-message',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
