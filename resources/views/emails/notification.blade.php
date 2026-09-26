@php $brand = \App\Support\Settings::get('business_name'); @endphp
<!DOCTYPE html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width"><title>{{ $subject }}</title></head>
<body style="margin:0;background:#f1f5f9;font-family:Segoe UI,Roboto,Helvetica,Arial,sans-serif;color:#1e293b">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="padding:24px 12px"><tr><td align="center">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;background:#ffffff;border-radius:12px;overflow:hidden;border:1px solid #e2e8f0">
<tr><td style="background:#0f2f8f;padding:20px 24px;color:#ffffff;font-size:18px;font-weight:700">{{ $brand }}</td></tr>
<tr><td style="padding:24px;font-size:15px;line-height:1.6">
{{-- Body is plain text from a template; escaped, then links made clickable. --}}
{!! preg_replace('~(https?://[^\s<]+)~', '<a href="$1" style="color:#1038b8">$1</a>', nl2br(e($body))) !!}
</td></tr>
<tr><td style="padding:16px 24px;border-top:1px solid #e2e8f0;font-size:12px;color:#64748b">You are receiving this because of a shipment with {{ $brand }}. Manage email preferences in your account.</td></tr>
</table></td></tr></table>
</body></html>
