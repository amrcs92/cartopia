<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-top:14px;font-size:15px;">
@foreach ($ticks as $tick)
<tr><td width="30" style="padding:5px 12px 5px 0;vertical-align:top;">
<table role="presentation" cellpadding="0" cellspacing="0" border="0"><tr>
<td class="chip" align="center" width="22" height="22" style="width:22px;height:22px;border-radius:999px;background-color:#E6EFE4;color:#1F5A3D;font-size:13px;font-weight:700;line-height:22px;">&#10003;</td>
</tr></table></td>
<td class="ink" style="padding:5px 0;color:#1A2B21;line-height:22px;">{{ $tick }}</td></tr>
@endforeach
</table>
