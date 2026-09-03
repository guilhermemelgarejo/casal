<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class DatabaseBackupNotificationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $databaseName,
        public string $fileName,
        public string $fileSize,
        public string $driveLink,
        public string $createdAt,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "✅ Backup Realizado - {$this->databaseName} ({$this->createdAt})",
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.database-backup',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
