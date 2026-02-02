<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px;">
    <h2 style="color: #1a56db; margin-bottom: 20px;">Erinnerung fällig</h2>

    <div style="background-color: #f9fafb; border-left: 4px solid #1a56db; padding: 15px; margin-bottom: 20px;">
        <h3 style="margin: 0 0 10px 0; color: #111;">{{ $reminder->title }}</h3>

        @if($reminder->description)
            <p style="margin: 0; color: #666;">{{ $reminder->description }}</p>
        @endif
    </div>

    <table style="width: 100%; border-collapse: collapse; margin-bottom: 20px;">
        <tr>
            <td style="padding: 8px 0; color: #666; width: 120px;">Fällig:</td>
            <td style="padding: 8px 0; font-weight: bold;">{{ $reminder->due_at->format('d.m.Y H:i') }} Uhr</td>
        </tr>
        <tr>
            <td style="padding: 8px 0; color: #666;">Priorität:</td>
            <td style="padding: 8px 0;">{{ $reminder->priority->getLabel() }}</td>
        </tr>
        @if($reminder->remindable)
            <tr>
                <td style="padding: 8px 0; color: #666;">Verknüpft mit:</td>
                <td style="padding: 8px 0;">
                    {{ class_basename($reminder->remindable_type) }}:
                    {{ $reminder->remindable->display_name ?? $reminder->remindable->title ?? $reminder->remindable->number ?? '-' }}
                </td>
            </tr>
        @endif
    </table>

    <p style="margin-top: 30px; color: #666; font-size: 12px; border-top: 1px solid #eee; padding-top: 15px;">
        Diese E-Mail wurde automatisch von Ihrem CRM versendet.
    </p>
</body>
</html>
