<!DOCTYPE html>
<html>
<head>
    <title>Invoice {{ $invoice->invoice_number }}</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 12px; }
        .invoice-container { max-width: 800px; margin: 0 auto; padding: 20px; }
        .header { display: flex; justify-content: space-between; margin-bottom: 30px; }
        .company-info { text-align: right; }
        .billing-section { display: flex; justify-content: space-between; margin: 30px 0; }
        .billing-box, .client-box { width: 48%; }
        .section-title { font-weight: bold; margin-bottom: 10px; border-bottom: 1px solid #000; padding-bottom: 5px; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f5f5f5; }
        .totals { float: right; margin-top: 20px; }
        .total-row { display: flex; justify-content: space-between; width: 300px; margin-bottom: 5px; }
        .total-row.final { font-weight: bold; font-size: 14px; border-top: 2px solid #000; padding-top: 10px; }
        .footer { margin-top: 50px; padding-top: 20px; border-top: 1px solid #ddd; text-align: center; font-size: 10px; }
        .status-badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 3px;
            font-size: 10px;
            font-weight: bold;
        }
        .status-draft { background: #f0f0f0; color: #666; }
        .status-sent { background: #e3f2fd; color: #1976d2; }
        .status-paid { background: #e8f5e9; color: #2e7d32; }
        .status-overdue { background: #ffebee; color: #c62828; }
        
        /* New styles for invoice header layout */
        .invoice-header-row {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
        }
        .invoice-title-section {
            flex: 1;
        }
        .company-info-section {
            text-align: left;
            margin-left: 40px;
        }
        .invoice-details {
            margin-top: 10px;
        }
        .company-name {
            font-size: 18px;
            font-weight: bold;
            color: #2c3e50;
            margin-bottom: 5px;
        }
    </style>
</head>
<body>
    <div class="invoice-container">
        <!-- Header - MODIFIED LAYOUT -->
        <div class="header">
            <div class="invoice-header-row">
                <div class="invoice-title-section">
                    <h1>INVOICE</h1>
                    <div class="invoice-details">
                        <p><strong>Invoice #:</strong> {{ $invoice->invoice_number }}</p>
                        <p><strong>Date:</strong> {{ $date }}</p>
                        <p><strong>Due Date:</strong> {{ $due_date }}</p>
                        <span class="status-badge status-{{ $invoice->status }}">
                            {{ strtoupper($invoice->status) }}
                        </span>
                    </div>
                </div>
                
                <!-- <div class="company-info-section">
                    <div class="company-name">{{ $company->name ?? 'Company Name' }}</div>
                    <p>{{ $company->address ?? 'Company Address' }}</p>
                    <p>Email: {{ $company->email ?? 'company@example.com' }}</p>
                    <p>Phone: {{ $company->phone ?? '+1 234 567 8900' }}</p>
                </div> -->
            </div>
        </div>

        <!-- Billing Information -->
        <div class="billing-section">
            <div class="billing-box">
                <div class="section-title">Bill To:</div>
                <p><strong>{{ $invoice->billing_name }}</strong></p>
                <p>Email: {{ $invoice->billing_email }}</p>
                <p>Phone: {{ $invoice->billing_phone }}</p>
                @if($invoice->candidate && $invoice->candidate->designation)
                <p>Designation: {{ $invoice->candidate->designation }}</p>
                @endif
            </div>
            
            <div class="client-box">
                <div class="section-title">Client:</div>
                @if($client)
                    <p><strong>{{ $client->name }}</strong></p>
                    @if($client->email)<p>Email: {{ $client->email }}</p>@endif
                    @if($client->phone)<p>Phone: {{ $client->phone }}</p>@endif
                    @if($client->address)<p>Address: {{ $client->address }}</p>@endif
                @else
                    <p>No client specified</p>
                @endif
            </div>
        </div>

        <!-- Job Information -->
        @if($invoice->job)
        <div class="section-title">Job Details:</div>
        <p><strong>Job Title:</strong> {{ $invoice->job->job_title }}</p>
        <p><strong>Period:</strong> {{ Carbon\Carbon::parse($invoice->period_start)->format('M d, Y') }} - {{ Carbon\Carbon::parse($invoice->period_end)->format('M d, Y') }}</p>
        @endif

        <!-- Invoice Items -->
        <div class="section-title">Invoice Items</div>
        <table>
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Description</th>
                    <th>Hours</th>
                    <th>Rate</th>
                    <th>Amount</th>
                </tr>
            </thead>
            <tbody>
                @foreach($items as $item)
                <tr>
                    <td>{{ Carbon\Carbon::parse($item['date'])->format('M d, Y') }}</td>
                    <td>{{ $item['description'] }}</td>
                    <td>{{ number_format($item['hours'], 2) }}</td>
                    <td>${{ number_format($item['rate'], 2) }}</td>
                    <td>${{ number_format($item['amount'], 2) }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>

        <!-- Totals -->
        <div class="totals">
            <div class="total-row">
                <span>Subtotal:</span>
                <span>${{ number_format($invoice->subtotal, 2) }}</span>
            </div>
            @if($invoice->tax_rate > 0)
            <div class="total-row">
                <span>Tax ({{ $invoice->tax_rate }}%):</span>
                <span>${{ number_format($invoice->tax_amount, 2) }}</span>
            </div>
            @endif
            @if($invoice->discount > 0)
            <div class="total-row">
                <span>Discount:</span>
                <span>-${{ number_format($invoice->discount, 2) }}</span>
            </div>
            @endif
            <div class="total-row final">
                <span>Total Amount:</span>
                <span>${{ number_format($invoice->total_amount, 2) }}</span>
            </div>
        </div>

        <!-- Summary -->
        <div style="margin-top: 100px;">
            <p><strong>Summary:</strong></p>
            <p>Total Hours: {{ number_format($invoice->total_hours, 2) }} hours</p>
            <p>Hourly Rate: ${{ number_format($invoice->hourly_rate, 2) }}/hour</p>
            @if($invoice->notes)
            <p><strong>Notes:</strong> {{ $invoice->notes }}</p>
            @endif
        </div>

        <!-- Footer -->
        <div class="footer">
            <p>Invoice generated by: {{ $employee->first_name ?? '' }} {{ $employee->last_name ?? '' }}</p>
            <p>Generated on: {{ Carbon\Carbon::now()->format('F d, Y h:i A') }}</p>
            <p>This is a computer-generated invoice. No signature required.</p>
        </div>
    </div>
</body>
</html>