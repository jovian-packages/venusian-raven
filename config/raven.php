<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Rendering Backend
    |--------------------------------------------------------------------------
    |
    | This option controls the renderer Raven draws with. "vulkan" uses the
    | Vulkan loader Raven opens itself (MoltenVK on macOS). "null" lays out
    | every widget but draws nothing, which is handy on CI machines.
    |
    | Supported: "vulkan", "null"
    |
    */

    'backend' => env('RAVEN_BACKEND', 'vulkan'),

    /*
    |--------------------------------------------------------------------------
    | Headless Mode
    |--------------------------------------------------------------------------
    |
    | When enabled, Raven runs on its null platform and opens no OS window
    | at all. Use this for tests and for machines without a display.
    |
    */

    'headless' => (bool) env('RAVEN_HEADLESS', false),

    /*
    |--------------------------------------------------------------------------
    | UI Scale
    |--------------------------------------------------------------------------
    |
    | A global scale applied on top of the monitor's own scale factor. Set
    | this to 0 to keep Raven's default of 1.0.
    |
    */

    'ui_scale' => (float) env('RAVEN_UI_SCALE', 0.0),

    /*
    |--------------------------------------------------------------------------
    | Theme
    |--------------------------------------------------------------------------
    |
    | The theme Raven applies once it starts. You may name one of Raven's
    | built-in themes or give the path to a Raven theme XML file. Leave it
    | null to keep Raven's default "Dark" theme.
    |
    | Supported: "Dark", "Light", "GlassDark", "GlassLight",
    |            "HighContrastDark", "HighContrastLight", or a file path
    |
    */

    'theme' => env('RAVEN_THEME'),

];
