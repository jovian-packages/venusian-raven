# jovian/venusian-raven

Raven toolkit driver for Venusian Topside, through ext-raven (Raven 0.2.3: a retained-mode GUI toolkit in C with its own Vulkan renderer and native Cocoa, X11 and Wayland windows).

Install it beside the other drivers and pick it:

```dotenv
TOOLKIT_BRIDGE_DEFAULT=raven
```

```php
// config/raven.php (all optional)
'backend' => env('RAVEN_BACKEND', 'vulkan'),   // or 'null': lays out, draws nothing
'headless' => env('RAVEN_HEADLESS', false),    // no OS window at all, for tests
'ui_scale' => env('RAVEN_UI_SCALE', 0.0),      // 0: Raven's default
'theme' => env('RAVEN_THEME'),                 // Dark (default), Light, GlassDark, GlassLight,
                                               // HighContrastDark, HighContrastLight, or a theme XML path
```

Publish it with `vendor:publish --tag=raven-config`; unset keys fall back to the package's defaults. An unknown theme fails at connect, listing the known ones.

Raven opens the Vulkan loader itself (MoltenVK on macOS, `brew install molten-vk vulkan-loader`). Without one, connecting fails with Raven's own reason.

## Session and pump

`RavenSession` starts Raven once per process (`Rinit_ex`). Raven has no descriptor watch, so `pump()` waits in `Rwait_frame()` slices of 8 ms and checks the loop's waiter descriptor (duplicated through `php://fd/N`, polled with `stream_select`) between them: a loop wake ends the pump within one slice. After each pump the session compares what Raven does not report: watched primitive sizes (`ViewResized`), each window's focused widget (`FocusGained`, `FocusLost`), the clipboard text (`ClipboardChanged`), split positions, tree expansions and item-view scroll offsets. Raven's own quit (its macOS application menu, the `app.quit` action) posts `QuitRequested`.

## Windows

A window is a Raven window created hidden and shown by `present()`. Its content widget is a vertical box holding the menu bar, when there is one, and the content container. The close button goes through `Rwindow_set_close_callback`: refused while a child or dialog is open, `WindowCloseRequested` while confirming, else the close path (content removed, `WindowClosed`). Resizes post `WindowResized` (latest wins) and scale changes `WindowScaleChanged`.

A child window (`open(…, parent)`) is a Raven dialog inside its parent (`Rdialog_open`): Raven dialogs are modal to their window, so the parent is blocked while it is open. Escape closes it; while it confirms close it still closes (Raven has already removed it) and posts `WindowCloseRequested` first.

## Menu bars and context menus

A menu profile becomes a Raven `MenuBar` widget at the top of the window. On macOS Raven shows it in the system bar while the window is active, after Raven's own application menu; elsewhere it is drawn in the window. Toggles are checkable items, radio groups checkable items kept exclusive by the driver, `keys` and `hotkey` become Raven shortcuts (Primary is Command on macOS, Ctrl elsewhere). Items post `MenuActivated`, `MenuToggled`, `MenuRadioChosen`; the About role opens an About dialog over the window; Quit posts `QuitRequested`. Edit roles have no Raven action: they send their key chord (Primary+C, …) to the window's focused widget and carry no Raven shortcut of their own, since the chord already reaches the widget.

`popupMenu()` and a right click on a primitive with a stored context menu open a Raven popup menu (`Rmenu_open`) with the same mail; the stored menu keeps its toggles and radios. A right press posts `RightClicked` for the innermost primitive under it, or `WindowRightClicked` outside every primitive.

## Primitives

Every primitive is a Raven border around its control: the border carries background, corner radius, opacity and padding, and is what its parent holds. Fill and align are the parent's slot: fill along a column's or row's axis is a fill coefficient, fill across it and align are the slot's alignment. Containers are Raven panels: `VBox`, `HBox`, `Grid` (cells, spans, row and column fill), `Canvas` for fixed frames, `ScrollBox`, `Splitter`, `TabView`.

Where Raven has no matching widget the driver builds one from Raven widgets:

| Topside | Raven |
|---|---|
| button image, aligned button text | the button's child content: a Raven image and label in a row or column |
| toggle button | a button in the primary style while pressed |
| toggle | a checkbox in its switch style |
| segmented control | a row of buttons, the selected one in the primary style |
| editable combo box | a text input and a button opening a popup menu of the options; completions open the same menu |
| spinner | a custom widget painting a turning ring of dots |
| progress bar with no fraction | the bar sweeping from empty to full on a timer |
| list and collection views | rows and cells of Raven widgets in a scroll box: image with badge, bold title with status icons, detail, action button; a double click activates |
| tree columns | the cells after the first follow it on the item's line; the first column's colour is the item's |
| date picker with no date | today's date shown, none reported until the user picks |
| font family | the family's .ttf/.otf file found in the system font folders (Raven loads fonts from files) |

## Not available on raven

These throw `Jovian\Toolkits\Raven\Exceptions\RavenIncompatibilityException`, naming what is missing:

- `TKScrollBar`: no standalone scroll bar widget.
- `TKVideo`: no media player widget.
- `TKWebView`: Raven 0.2.3 is built without its web pages.
- Text areas: coloured line backgrounds and range styles, `scrollTo()` and `scrollPosition()` (Raven's editor colours text through its syntax languages only and reports no scroll offset), alignment other than left.
- Text inputs aligned other than left.
- A scroll view with neither scroll bar.
- A font family with no font file installed.
- `fontMetrics()`: Raven reports text width and height but no ascent, descent or pitch.

## Dialogs, clipboard, desktop

Alerts are Raven dialogs inside the parent window: title, message, detail, suppression checkbox and the buttons, the default in the primary style; Escape answers the cancel button. Raven has no alert icons, so an alert's style is not shown. File requests use Raven's platform panels (`Rfile_dialog_open`, `…_save`, `…_select_folder`, with filters, multiple selection and hidden files), run from the next frame because Raven runs them modally. The clipboard is Raven's text clipboard; the desktop opens files and addresses with `Ropen_url`. The system accent is Raven's theme accent.
