<?php

namespace App\Mail;

use App\Models\Invite;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TeamInviteMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Invite $invite) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'You\'ve been invited to Scarlet');
    }

    public function content(): Content
    {
        return new Content(view: 'mail.team-invite');
    }
}
