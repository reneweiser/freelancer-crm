<?php

namespace App\Mail;

use App\Models\Project;
use App\Services\PdfService;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Attachment;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OfferMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Project $project,
        public string $emailSubject,
        public string $bodyText
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->emailSubject,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.offer',
            with: [
                'bodyText' => $this->bodyText,
                'project' => $this->project,
            ],
        );
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        $pdf = app(PdfService::class)->generateOfferPdf($this->project);
        $reference = $this->project->reference ?: $this->project->id;

        return [
            Attachment::fromData(fn () => $pdf->output(), "Angebot-{$reference}.pdf")
                ->withMime('application/pdf'),
        ];
    }
}
