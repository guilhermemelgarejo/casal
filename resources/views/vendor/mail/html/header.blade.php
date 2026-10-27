@props(['url'])
<tr>
<td class="header">
<a href="{{ $url }}" class="brand-link">
<img src="{{ asset('images/duozen-logo.png') }}" class="logo" width="280" height="112" alt="{{ trim($slot) ?: 'DuoZen' }}">
</a>
<p class="brand-tagline">Finanças a dois, em paz.</p>
</td>
</tr>
