<?php

namespace App\Mail;

use App\Models\Couple;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ContactMessageMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $name,
        public string $email,
        public ?string $contactSubject,
        public string $messageBody,
        public ?User $user = null,
        public ?Couple $couple = null,
    ) {
    }

    public function envelope(): Envelope
    {
        $subject = $this->contactSubject ?: 'Mensagem de contato';

        return new Envelope(
            subject: 'Contato DuoZen: '.$subject,
            replyTo: [
                new Address($this->email, $this->name),
            ],
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.contact-message',
        );
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
