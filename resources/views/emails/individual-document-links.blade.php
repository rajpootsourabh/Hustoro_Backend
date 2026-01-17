<!DOCTYPE html>
<html>
<head>
    <title>Document Completion Links</title>
    <style>
        body { 
            font-family: Arial, sans-serif; 
            line-height: 1.6; 
            color: #333;
            margin: 0;
            padding: 20px;
        }
        .container { 
            max-width: 600px; 
            margin: 0 auto; 
            padding: 30px;
        }
        .document-list { 
            margin: 20px 0; 
        }
        .document-item { 
            padding: 15px; 
            border: 1px solid #ddd;
            border-radius: 8px;
            margin-bottom: 10px;
            background: #f9f9f9;
        }
        .document-link {
            word-break: break-all;
            background: #fff;
            padding: 10px;
            border-radius: 4px;
            border: 1px dashed #ccc;
            margin-top: 8px;
            font-size: 12px;
        }
        .copy-btn {
            background: #046449;
            color: white;
            border: none;
            padding: 4px 8px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 11px;
            margin-left: 8px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h2>Hello {{ $candidate->first_name }},</h2>
        
        <p>You have {{ $total_documents }} document(s) that need to be completed for the <strong>{{ $stage->name }}</strong> stage.</p>
        
        @if($custom_message)
        <p><strong>Additional Message:</strong> {{ $custom_message }}</p>
        @endif

        <div class="document-list">
            <h3>Document Links:</h3>
            <p><em>Each link is unique and can only be used once. Click the link to access and complete each document.</em></p>
            
            @foreach($document_links as $link)
            <div class="document-item">
                <strong>{{ $link['document_name'] }}</strong>
                @if($link['document_description'])
                <br><small>{{ $link['document_description'] }}</small>
                @endif
                <div class="document-link">
                    {{ $link['url'] }}
                    <button class="copy-btn" onclick="navigator.clipboard.writeText('{{ $link['url'] }}')">Copy</button>
                </div>
            </div>
            @endforeach
        </div>

        <p style="margin-top: 20px; font-size: 12px; color: #666;">
            <em>Each link will expire in 7 days and can only be used once. If you have any questions, please contact your recruiter.</em>
        </p>

        <p>Best regards,<br>Hustoro, HR Team</p>
    </div>
</body>
</html>