<?php

use App\Models\User;

// The admin theme (resources/css/filament/admin/theme.css) is built by Vite,
// so this needs `npm run build` after the theme changes.

/**
 * JavaScript for an element's computed colour as rgb(r, g, b). Filament's
 * palette colours compute to oklch(), so they go through a canvas pixel.
 */
function panelColour(string $selector, string $property = 'backgroundColor'): string
{
    return <<<JS
        function () {
            const context = document.createElement('canvas').getContext('2d');
            context.fillStyle = getComputedStyle(document.querySelector('{$selector}')).{$property};
            context.fillRect(0, 0, 1, 1);
            const [r, g, b] = context.getImageData(0, 0, 1, 1).data;
            return `rgb(\${r}, \${g}, \${b})`;
        }
        JS;
}

it('gives the admin panel the store look in light and dark mode', function () {
    $this->actingAs(User::factory()->admin()->create());

    visit('/admin')->inLightMode()
        ->assertScript(panelColour('.fi-sidebar'), 'rgb(34, 56, 43)')
        ->assertScript(panelColour('.fi-topbar'), 'rgb(34, 56, 43)')
        ->assertScript(panelColour('.fi-sidebar-item.fi-active > .fi-sidebar-item-btn'), 'rgb(245, 180, 46)')
        ->assertScript(panelColour('.fi-body'), 'rgb(237, 242, 228)')
        ->assertScript("getComputedStyle(document.querySelector('.fi-header-heading')).fontFamily.startsWith('\"Young Serif\"')")
        ->assertNoJavaScriptErrors();

    visit('/admin')->inDarkMode()
        ->assertScript(panelColour('.fi-sidebar'), 'rgb(34, 56, 43)')
        ->assertScript(panelColour('.fi-body'), 'rgb(17, 25, 21)')
        ->assertScript(panelColour('.fi-wi-stats-overview-stat'), 'rgb(26, 37, 31)');

    // The store green behind primary buttons, and a card's offset shadow.
    visit('/admin/products')->inLightMode()
        ->assertScript(panelColour('.fi-header .fi-btn.fi-color-primary'), 'rgb(31, 90, 61)')
        ->assertScript("getComputedStyle(document.querySelector('.fi-ta-ctn')).boxShadow", 'rgb(31, 90, 61) 5px 5px 0px 0px');
});
