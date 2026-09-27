<!doctype html>
<html lang="{{ $locale ?? 'az' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="color-scheme" content="light">
    <!--[if mso]>
    <noscript><xml><o:OfficeDocumentSettings><o:PixelsPerInch>96</o:PixelsPerInch></o:OfficeDocumentSettings></xml></noscript>
    <![endif]-->
</head>
<body style="margin:0;padding:0;background:#f3f1f8;font-family:-apple-system,'Segoe UI',Roboto,Arial,sans-serif;color:#1a1520;-webkit-font-smoothing:antialiased">

{{-- Outer wrapper --}}
<table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="background:#f3f1f8;padding:40px 16px">
    <tr><td align="center">

            {{-- Card --}}
            <table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="max-width:580px">

                {{-- Header --}}
                <tr>
                    <td style="background:#2b1f4a;padding:28px 36px;text-align:center">
                        <a href="{{ config('app.url') }}"
                           style="display:inline-block;color:#ffffff;text-decoration:none;
                      font-family:Georgia,'Times New Roman',serif;
                      font-size:22px;letter-spacing:2px;font-weight:normal">
                            ParfumShop<span style="color:#bda4e5">.az</span>
                        </a>
                        <div style="margin-top:4px;color:#a99bc4;font-size:10px;letter-spacing:3px;text-transform:uppercase;font-family:Georgia,'Times New Roman',serif">
                            By Image, Since 2000
                        </div>
                    </td>
                </tr>

                {{-- Thin accent line --}}
                <tr><td style="height:3px;background:linear-gradient(90deg,#2b1f4a,#bda4e5,#2b1f4a)"></td></tr>

                {{-- Body --}}
                <tr>
                    <td style="background:#ffffff;padding:44px 40px">
                        @yield('body')
                    </td>
                </tr>

                {{-- Thin accent line --}}
                <tr><td style="height:1px;background:#ece9f2"></td></tr>

                {{-- Footer --}}
                <tr>
                    <td style="background:#2b1f4a;padding:20px 36px;text-align:center">
                        <p style="margin:0 0 6px;color:#a99bc4;font-size:11px;letter-spacing:1.5px;text-transform:uppercase">
                            © {{ date('Y') }} ParfumShop.az
                        </p>
                        <a href="{{ config('app.url') }}"
                           style="color:#bda4e5;font-size:11px;text-decoration:none;letter-spacing:0.5px">
                            {{ config('app.url') }}
                        </a>
                    </td>
                </tr>

            </table>
        </td></tr>
</table>

</body>
</html>
