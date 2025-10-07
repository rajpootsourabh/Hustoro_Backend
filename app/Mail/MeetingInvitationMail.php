<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class MeetingInvitationMail extends Mailable
{
    use Queueable, SerializesModels;

    public $meetingUrl;
    public $scheduledAt;
    public $meetingPassword;

    public function __construct($meetingUrl, $scheduledAt, $meetingPassword)
    {
        $this->meetingUrl = $meetingUrl;
        $this->scheduledAt = $scheduledAt;
        $this->meetingPassword = $meetingPassword;
    }

    public function build()
    {
        return $this->subject('Interview Meeting Invitation')
            ->view('emails.meeting_invitation')
            ->with([
                'meetingUrl' => $this->meetingUrl,
                'scheduledAt' => $this->scheduledAt,
                'meetingPassword' => $this->meetingPassword,
            ]);
    }
}
