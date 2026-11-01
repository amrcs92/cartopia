<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="color-scheme" content="light dark">
<meta name="supported-color-schemes" content="light dark">
<title>@yield('title')</title>
<style>
@media (prefers-color-scheme: dark) {
    .bg-paper { background-color: #111915 !important; }
    .bg-surface { background-color: #1A251F !important; border-color: #E9E3D0 !important; }
    .shadow-card { background-color: #2E7A54 !important; }
    .bg-slip { background-color: #223029 !important; }
    .ink { color: #F1EEE3 !important; }
    .body { color: #C9D3CB !important; }
    .muted { color: #B3C0B6 !important; }
    .line { border-color: #344A3D !important; }
    .line2 { border-color: #4A6455 !important; }
    .outline { border-color: #E9E3D0 !important; }
    .btn { background-color: #F5B42E !important; color: #14201A !important; border-color: #E9E3D0 !important; }
    .badge { background-color: #F5B42E !important; color: #14201A !important; border-color: #E9E3D0 !important; }
}
@media (max-width: 480px) {
    .pad { padding: 20px !important; }
    .gutter { padding-left: 16px !important; padding-right: 16px !important; }
}
</style>
</head>
<body class="bg-paper" style="margin:0;padding:0;background-color:#EDF2E4;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" class="bg-paper" style="background-color:#EDF2E4;">
<tr><td align="center" style="padding:0 0 36px;">
<table role="presentation" width="640" cellpadding="0" cellspacing="0" border="0" class="ink" style="width:100%;max-width:640px;font-family:-apple-system,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;color:#1A2B21;">

<tr><td class="gutter" style="padding:28px 32px 0;">
<table role="presentation" cellpadding="0" cellspacing="0" border="0"><tr>
<td style="vertical-align:middle;padding-right:10px;"><img src="{{ asset('favicon-32.png') }}" width="32" height="32" alt="" style="display:block;border:0;"></td>
<td class="ink" style="vertical-align:middle;font-family:Georgia,'Times New Roman',serif;font-size:26px;color:#1A2B21;">Cartopia</td>
</tr></table>
</td></tr>

<tr><td class="gutter" style="padding:20px 32px 0;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"><tr>
<td class="shadow-card" style="background-color:#1F5A3D;border-radius:22px;padding:0 5px 5px 0;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"><tr>
<td class="bg-surface outline pad" style="background-color:#FFFFFF;border:2.5px solid #1A2B21;border-radius:22px;padding:28px;">

<table role="presentation" cellpadding="0" cellspacing="0" border="0"><tr>
<td style="vertical-align:middle;padding-right:12px;">
<table role="presentation" cellpadding="0" cellspacing="0" border="0"><tr>
<td class="badge outline" align="center" width="36" height="36" style="width:36px;height:36px;border-radius:999px;border:2px solid #1A2B21;background-color:#F5B42E;color:#1A2B21;font-size:18px;font-weight:700;line-height:36px;">&#10003;</td>
</tr></table>
</td>
<td class="ink" style="vertical-align:middle;font-family:Georgia,'Times New Roman',serif;font-weight:400;font-size:28px;line-height:1.2;color:#1A2B21;">@yield('title')</td>
</tr></table>

<p class="body" style="margin:14px 0 22px;font-size:16px;line-height:1.55;color:#3D4F43;">@yield('intro')</p>

@yield('content')

<a href="@yield('url')" class="btn outline" style="display:inline-block;margin-top:26px;text-decoration:none;background-color:#F5B42E;color:#1A2B21;font-weight:800;font-size:16px;padding:14px 24px;border:2.5px solid #1A2B21;border-radius:14px;">@yield('button')</a>

</td></tr></table>
</td></tr></table>
</td></tr>

<tr><td class="gutter muted" style="padding:22px 32px 0;font-size:13px;line-height:1.6;color:#4D5E52;">Cartopia, a small online market for everyday staples.<br>You're getting this email because you have a Cartopia account.</td></tr>

</table>
</td></tr>
</table>
</body>
</html>
