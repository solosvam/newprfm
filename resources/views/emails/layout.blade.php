<!doctype html>
<html lang="{{ $locale }}">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>
<body style="margin:0;background:#f7f5f2;font-family:Arial,Helvetica,sans-serif;color:#28221e">
<table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="background:#f7f5f2;padding:30px 12px">
<tr><td align="center">
<table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="max-width:600px;background:#fff;border:1px solid #eee6df">
<tr><td style="padding:30px 32px;border-bottom:1px solid #eee6df;text-align:center"><a href="{{ config('app.url') }}" style="color:#28221e;text-decoration:none;font-size:26px;letter-spacing:2px;font-family:Georgia,serif">PARFUMSHOP.AZ</a></td></tr>
<tr><td style="padding:32px">@yield('body')</td></tr>
<tr><td style="padding:22px 32px;border-top:1px solid #eee6df;color:#777;font-size:12px;text-align:center">© {{ date('Y') }} ParfumShop.az<br><a href="{{ config('app.url') }}" style="color:#777">{{ config('app.url') }}</a></td></tr>
</table></td></tr></table>
</body></html>
