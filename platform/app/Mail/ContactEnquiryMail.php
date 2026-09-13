<?php
namespace App\Mail;
use App\Models\ContactMessage;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\{Content,Envelope,Address};
class ContactEnquiryMail extends Mailable
{
    public function __construct(public ContactMessage $enquiry) {}
    public function envelope(): Envelope
    {
        return new Envelope(subject:preg_replace('/[\r\n]+/',' ',$this->enquiry->subject),replyTo:[new Address($this->enquiry->email,preg_replace('/[\r\n]+/',' ',$this->enquiry->name))]);
    }
    public function content(): Content {return new Content(view:'mail.contact-enquiry');}
}
