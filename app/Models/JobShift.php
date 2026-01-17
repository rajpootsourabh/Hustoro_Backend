<?php
// app/Models/JobShift.php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class JobShift extends Model
{
    use HasFactory;

    protected $table = 'job_shifts';

    protected $fillable = ['job_id', 'shift_id'];

    public $timestamps = true;

    public function job()
    {
        return $this->belongsTo(Job::class);
    }

    public function shift()
    {
        return $this->belongsTo(Shift::class);
    }
}