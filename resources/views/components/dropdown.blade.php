@props(['align' => 'right', 'contentClasses' => ''])

<div class="dropdown">
    {{ $trigger }}
    <ul class="dropdown-menu {{ $align === 'right' ? 'dropdown-menu-end' : '' }} {{ $contentClasses }}">
        {{ $content }}
    </ul>
</div>
