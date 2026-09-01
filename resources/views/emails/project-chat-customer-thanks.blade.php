@php
    $projectUrl = match ($portal) {
        'employee' => route('employee.projects.show', $project),
        'customer' => route('customer.projects.show', $project),
        default => route('pm.projects.show', $project),
    };
    $senderName = $comment->author_name ?: $customer->name ?: 'there';
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Thanks for your message</title>
</head>
<body style="margin:0;padding:24px;background:#f8fafc;font-family:Arial,Helvetica,sans-serif;color:#0f172a;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:640px;margin:0 auto;background:#ffffff;border-radius:18px;overflow:hidden;border:1px solid #e2e8f0;">
        <tr>
            <td style="padding:24px 28px;background:#14532d;color:#ffffff;">
                <p style="margin:0 0 8px;font-size:12px;letter-spacing:.08em;text-transform:uppercase;opacity:.8;">Project Support</p>
                <h1 style="margin:0;font-size:24px;line-height:1.3;">Thanks for your message, {{ $customer->name ?? 'Customer' }}</h1>
            </td>
        </tr>
        <tr>
            <td style="padding:28px;">
                <p style="margin:0 0 16px;font-size:15px;line-height:1.7;">
                    Hi {{ $senderName }}, we received your update for <strong>{{ $project->name }}</strong>.
                </p>
                <p style="margin:0 0 16px;font-size:15px;line-height:1.7;">
                    Our project team has been notified and will review your message shortly.
                </p>
                <div style="padding:18px;border-radius:16px;background:#f0fdf4;border:1px solid #bbf7d0;">
                    <p style="margin:0 0 10px;font-size:12px;color:#166534;text-transform:uppercase;letter-spacing:.06em;">Your message</p>
                    <p style="margin:0;font-size:15px;line-height:1.75;white-space:pre-line;">{{ $comment->body }}</p>
                </div>

                <div style="margin-top:24px;">
                    <a href="{{ $projectUrl }}" style="display:inline-block;padding:12px 18px;border-radius:999px;background:#15803d;color:#ffffff;text-decoration:none;font-weight:700;">Open Project</a>
                </div>

                <p style="margin:24px 0 0;font-size:13px;line-height:1.6;color:#64748b;">
                    Thank you for staying in touch with the project team.
                </p>
            </td>
        </tr>
    </table>
</body>
</html>
