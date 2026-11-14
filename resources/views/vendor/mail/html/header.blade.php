@props(['url'])
{{-- Laravel's mail header with the store logo above the name. An absolute URL, because Gmail drops data: images. --}}
<tr>
<td class="header">
<a href="{{ $url }}" style="display: inline-block;">
<img src="{{ rtrim(config('app.url'), '/') }}/images/logo-saefulaskar.png" class="logo" width="64" height="64" alt="{{ config('app.name') }}" style="height: 64px; width: 64px; max-height: 64px; margin: 0 auto 8px; display: block; border: 0;">
{!! $slot !!}
</a>
</td>
</tr>
