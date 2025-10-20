<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ContactConfirmationMail extends Mailable
{
    use Queueable, SerializesModels;

    public $contactData;
    public $name;
    public $subject;

    /**
     * Create a new message instance.
     */
    public function __construct($contactData)
    {
        $this->contactData = $contactData;
        $this->name = $contactData['name'] ?? 'Customer';
        $this->subject = $contactData['subject'] ?? 'Your inquiry';
    }

    /**
     * Build the message.
     */
    public function build()
    {
        return $this->subject('Thank you for contacting BookBharat')
                    ->view('emails.default')
                    ->with([
                        'contact_data' => $this->contactData,
                        'name' => $this->name,
                        'message' => "Thank you for reaching out to us. We have received your message regarding '{$this->subject}' and our team will get back to you within 24-48 hours.",
                        'is_confirmation' => true,
                    ]);
    }
}

