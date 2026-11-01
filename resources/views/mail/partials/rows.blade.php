<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-top:18px;font-size:15px;">
@foreach ($rows as $row)
<tr><td class="muted" style="padding:4px 20px 4px 0;color:#4D5E52;white-space:nowrap;vertical-align:top;">{{ $row['label'] }}</td>
<td class="ink" align="right" style="padding:4px 0;color:#1A2B21;font-weight:{{ $row['bold'] ? '700' : '400' }};">{{ $row['value'] }}</td></tr>
@endforeach
</table>
