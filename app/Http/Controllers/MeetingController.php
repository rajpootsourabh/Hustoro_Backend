<?php 

namespace App\Http\Controllers;

use App\Models\Meeting;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Mail;
use App\Mail\MeetingInvitationMail;

class MeetingController extends Controller
{
    public function schedule(Request $request)
    {
        $request->validate([
            'employee_id' => 'required|integer',
            'candidate_id' => 'required|integer',
            'candidate_email' => 'required|email',
            'scheduled_at' => 'required|date',
        ]);

        // Generate unique Jitsi meeting link & password
        $meetingId = 'interview-' . Str::random(20);
        $meetingUrl = "https://meet.jit.si/{$meetingId}";
        $meetingPassword = Str::random(8); // strong 8-character password

        // Save in DB
        $meeting = Meeting::create([
            'employee_id' => $request->employee_id,
            'candidate_id' => $request->candidate_id,
            'meeting_link' => $meetingUrl,
            'meeting_password' => $meetingPassword,
            'scheduled_at' => $request->scheduled_at,
        ]);

        // Send email
        Mail::to($request->candidate_email)
            ->send(new MeetingInvitationMail($meetingUrl, $request->scheduled_at, $meetingPassword));

        return response()->json([
            'message' => 'Meeting scheduled successfully',
            'meeting_link' => $meetingUrl,
            'meeting_password' => $meetingPassword, // optional in API response
        ]);
    }
}
