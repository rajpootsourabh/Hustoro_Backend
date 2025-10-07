<?php

namespace App\Notifications;

use App\Models\TimeOffRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class TimeOffRequested extends Notification
{
    use Queueable;

    protected $timeOff;
    protected $status;

    public function __construct(TimeOffRequest $timeOff, string $status)
    {
        $this->timeOff = $timeOff;
        $this->status = $status;
    }

    public function via($notifiable): array
    {
        return ['database']; // Remove 'broadcast' from here
    }

    public function toDatabase($notifiable): array
    {
        return [
            'title' => 'Time-Off ' . ucfirst($this->status),
            'message' => 'Your time-off request from ' . $this->timeOff->start_date .
                ' to ' . $this->timeOff->end_date . ' has been ' . $this->status . '.',
            'time_off_request_id' => $this->timeOff->id,
        ];
    }

    // Remove the toBroadcast method completely
    // Remove the broadcastOn method completely
    // Remove the broadcastAs method completely
}