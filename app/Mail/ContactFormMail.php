<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ContactFormMail extends Mailable
{
    use Queueable, SerializesModels;

    public $contactData;
    public $name;
    public $email;
    public $subject;
    public $message;

    /**
     * Create a new message instance.
     */
    public function __construct($contactData)
    {
        $this->contactData = $contactData;
        $this->name = $contactData['name'] ?? 'Guest';
        $this->email = $contactData['email'] ?? 'no-reply@example.com';
        $this->subject = $contactData['subject'] ?? 'Contact Form Submission';
        $this->message = $contactData['message'] ?? '';
    }

    /**
     * Build the message.
     */
    public function build()
    {
        return $this->subject('New Contact Form Submission: ' . $this->subject)
                    ->view('emails.default')
                    ->with([
                        'contact_data' => $this->contactData,
                        'name' => $this->name,
                        'email' => $this->email,
                        'user_message' => $this->message,
                        'subject' => $this->subject,
                    ]);
    }
}

