<?php
namespace App\Mail;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\{Content,Envelope};
class PublicLiveReminderMail extends Mailable
{
    public function __construct(public string $eventTitle,public string $eventDate,public string $eventUrl) {}
    public function envelope(): Envelope
    {
        return new Envelope(subject:__('public.reminder_mail_subject',['title'=>preg_replace('/[\r\n]+/',' ',$this->eventTitle)]));
    }
    public function content(): Content {return new Content(text:'mail.public-live-reminder');}
}
