<?php

namespace App\Mail;

use App\Models\Comment;
use App\Models\Customer;
use App\Models\Project;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

class ProjectChatCustomerThanksMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Project $project,
        public Comment $comment,
        public Customer $customer,
        public ?string $portal = 'customer',
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Thanks for your project message: ' . Str::limit($this->project->name, 60),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.project-chat-customer-thanks',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
