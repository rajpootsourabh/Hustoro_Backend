<?php
// app/Models/Invoice.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Invoice extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'invoice_number',
        'company_id',
        'candidate_id',
        'job_id',
        'client_id', // Make sure this is in fillable
        'invoice_date',
        'due_date',
        'status',
        'billing_name',
        'billing_email',
        'billing_phone',
        'billing_address',
        'client_details', // For storing client details as JSON
        'items',
        'subtotal',
        'tax_rate',
        'tax_amount',
        'discount',
        'total_amount',
        'total_hours',
        'hourly_rate',
        'period_start',
        'period_end',
        'payment_method',
        'payment_date',
        'notes',
        'employee_id',
        'created_by',
        'pdf_path'
    ];

    protected $casts = [
        'items' => 'array',
        'client_details' => 'array', // Cast client_details to array
        'invoice_date' => 'date',
        'due_date' => 'date',
        'period_start' => 'date',
        'period_end' => 'date',
        'payment_date' => 'date',
        'subtotal' => 'decimal:2',
        'tax_rate' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'discount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'total_hours' => 'decimal:2',
        'hourly_rate' => 'decimal:2',
    ];

    // Relationships
    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function candidate()
    {
        return $this->belongsTo(Candidate::class);
    }

    public function job()
    {
        return $this->belongsTo(Job::class);
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // Accessor for formatted items
    public function getFormattedItemsAttribute()
    {
        return collect($this->items)->map(function ($item) {
            return [
                'description' => $item['description'] ?? '',
                'hours' => $item['hours'] ?? 0,
                'rate' => $item['rate'] ?? $this->hourly_rate,
                'amount' => $item['amount'] ?? 0,
                'date' => $item['date'] ?? null,
            ];
        });
    }

    // Generate invoice number
    public static function generateInvoiceNumber($companyId)
    {
        $prefix = 'INV-' . date('Y') . '-';
        $lastInvoice = self::where('company_id', $companyId)
            ->where('invoice_number', 'like', $prefix . '%')
            ->orderBy('invoice_number', 'desc')
            ->first();

        if ($lastInvoice) {
            $lastNumber = intval(substr($lastInvoice->invoice_number, -4));
            $newNumber = str_pad($lastNumber + 1, 4, '0', STR_PAD_LEFT);
        } else {
            $newNumber = '0001';
        }

        return $prefix . $newNumber;
    }

    // Accessor for client details
    public function getClientDetailsAttribute($value)
    {
        if (!$value) {
            // If client_details is null but we have a client_id, return client data
            if ($this->client_id && $this->client) {
                return [
                    'id' => $this->client->id,
                    'name' => $this->client->name,
                    'email' => $this->client->email,
                    'phone' => $this->client->contact_number,
                    'address' => $this->client->address,
                ];
            }
            return null;
        }
        
        return is_array($value) ? $value : json_decode($value, true);
    }
    // Setter for client details
    public function setClientDetailsAttribute($value)
    {
        $this->attributes['client_details'] = is_array($value) ? json_encode($value) : $value;
    }
}
