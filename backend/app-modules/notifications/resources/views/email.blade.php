<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="color-scheme" content="light dark">
<title>{{ $subject }}</title>
</head>
<body style="margin:0;padding:0;background:{{ $colors['bg'] }};color:{{ $colors['ink'] }};font-family:'{{ $font }}',Arial,Helvetica,sans-serif;font-size:16px;line-height:1.5;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:{{ $colors['bg'] }};">
<tr><td align="center" style="padding:32px 16px;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;background:{{ $colors['surface'] }};border:1px solid {{ $colors['line'] }};border-radius:{{ $radius + 4 }}px;overflow:hidden;">
<tr><td style="height:8px;background:{{ $colors['accent'] }};font-size:0;line-height:0;">&nbsp;</td></tr>
<tr><td style="padding:24px 32px 8px;">
@if ($logoUrl)
<img src="{{ $logoUrl }}" alt="{{ $editionName }}" height="40" style="display:block;height:40px;width:auto;border:0;">
@else
<strong style="font-size:18px;color:{{ $colors['ink'] }};">{{ $editionName }}</strong>
@endif
</td></tr>
<tr><td style="padding:16px 32px 8px;">
<h1 style="margin:0 0 16px;font-size:22px;line-height:1.25;color:{{ $colors['ink'] }};">{{ $subject }}</h1>
@foreach ($paragraphs as $paragraph)
<p style="margin:0 0 14px;color:{{ $colors['ink'] }};">{!! $paragraph !!}</p>
@endforeach
</td></tr>
@if ($actionUrl)
<tr><td style="padding:8px 32px 28px;">
<a href="{{ $actionUrl }}" style="display:inline-block;padding:12px 22px;background:{{ $colors['brand'] }};color:{{ $colors['on_brand'] }};border-radius:{{ $radius }}px;font-weight:600;text-decoration:none;">{{ $actionLabel }}</a>
</td></tr>
@endif
<tr><td style="padding:16px 32px 24px;border-top:1px solid {{ $colors['line'] }};font-size:13px;color:{{ $colors['ink_2'] }};">
{{ $footer }}
@if ($unsubscribeUrl)
<br><a href="{{ $unsubscribeUrl }}" style="color:{{ $colors['link'] }};">Não quero mais receber estes avisos</a>
@endif
</td></tr>
</table>
</td></tr>
</table>
</body>
</html>
