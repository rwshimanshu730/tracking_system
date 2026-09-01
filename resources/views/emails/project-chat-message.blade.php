@php
    $projectUrl = match ($portal) {
        'employee' => route('employee.projects.show', $project),
        'customer' => route('customer.projects.show', $project),
        default => route('pm.projects.show', $project),
    };
    $senderName = $comment->author_name ?: $comment->sender?->name ?: 'Someone';
    $attachments = collect($comment->attachments ?? []);
    $audienceLabel = $audience === 'customer' ? 'customer' : 'team';
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Project Chat Update</title>
</head>
<body style="margin:0;padding:24px;background:#f8fafc;font-family:Arial,Helvetica,sans-serif;color:#0f172a;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:640px;margin:0 auto;background:#ffffff;border-radius:18px;overflow:hidden;border:1px solid #e2e8f0;">
        <tr>
            <td style="padding:24px 28px;background:#0f172a;color:#ffffff;">
                <p style="margin:0 0 8px;font-size:12px;letter-spacing:.08em;text-transform:uppercase;opacity:.75;">Project Chat</p>
                <h1 style="margin:0;font-size:24px;line-height:1.3;">New {{ $audienceLabel }} message in {{ $project->name }}</h1>
            </td>
        </tr>
        <tr>
            <td style="padding:28px;">
                <p style="margin:0 0 16px;font-size:15px;line-height:1.7;">
                    <strong>{{ $senderName }}</strong> posted a new message in the project chat.
                </p>
                <div style="padding:18px;border-radius:16px;background:#f8fafc;border:1px solid #e2e8f0;">
                    <p style="margin:0 0 10px;font-size:12px;color:#64748b;text-transform:uppercase;letter-spacing:.06em;">Message</p>
                    <p style="margin:0;font-size:15px;line-height:1.75;white-space:pre-line;">{{ $comment->body }}</p>
                </div>

                @if($attachments->isNotEmpty())
                    <div style="margin-top:18px;padding:16px;border-radius:16px;background:#fff7ed;border:1px solid #fed7aa;">
                        <p style="margin:0 0 8px;font-size:13px;font-weight:700;color:#9a3412;">Attachments included</p>
                        <p style="margin:0;font-size:14px;line-height:1.6;color:#7c2d12;">
                            {{ $attachments->count() }} file{{ $attachments->count() > 1 ? 's were' : ' was' }} attached with this message.
                        </p>
                    </div>
                @endif

                <div style="margin-top:24px;">
                    <a href="{{ $projectUrl }}" style="display:inline-block;padding:12px 18px;border-radius:999px;background:#2563eb;color:#ffffff;text-decoration:none;font-weight:700;">Open Project Chat</a>
                </div>

                <p style="margin:24px 0 0;font-size:13px;line-height:1.6;color:#64748b;">
                    You are receiving this email because this project chat message was shared with your group.
                </p>
            </td>
        </tr>
    </table>
</body>
</html>
