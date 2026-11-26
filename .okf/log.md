# Log

## 2026-11-26

* **Update**: [Menu bar](/architecture/menu-bar.md): `StandardKind::PASTE_AND_MATCH_STYLE` sends Paste's chord. [Session](/architecture/session.md): `applyColorScheme()` picks the dark or light member of the theme's family. [UI primitives](/architecture/ui-primitives.md): an empty-label button with an icon holds the icon alone; frameless buttons are solid material, so no hover glass.
* **Update**: [UI primitives](/architecture/ui-primitives.md): `RavenScrollView` reports scrolling: Raven has no scroll callback, so while watched a 30 Hz timer compares `Rscroll_box_get_offset` (as the item views do); the position is the box's offset and rect against what its child asks to be. 6 real-window checks on 8.4 NTS and ZTS.
* **Update**: [UI primitives](/architecture/ui-primitives.md), [Window](/architecture/window.md): `ButtonStyle::HELP` drawn as DEFAULT (Raven has no help button); `openUrl()` through `Ropen_url`. Checked in the real app on 8.4 NTS and ZTS.

## 2026-11-25

* **Update**: [Window](/architecture/window.md): alerts (topside 85ef6af), a Raven dialog laid out as `Rmessage_box`. 18 real-window checks on 8.4 NTS and ZTS.
* **Fix**: [UI primitives](/architecture/ui-primitives.md): a fitted picture's height follows its width (once per frame before the layout, through Raven's frame callback: `RavenFrames`); a picture with differing corners is drawn by the border's effect inside its four radii (`resources/shaders/picture.spv`), on the Vulkan backend. Checked in a real app on both builds; the Windowless suite passes.
* **Update**: [UI primitives](/architecture/ui-primitives.md): `RavenWebView` on topside's `TKWebView` (c0bb08a) over Raven's system web view (WKWebView on macOS; `supported()` false elsewhere), loaded and failed from Raven's event callback, the YouTube embed shown; button padding as Raven's padding metrics at 0 and each side on the content's slot (per side, with an icon box too; the padding replaces the theme's). 26 real-window checks on 8.4 NTS and ZTS.

* **Correction**: [UI primitives](/architecture/ui-primitives.md): in a glass theme a set background showed as frosted glass with what is behind blurred through it; a widget given a background is now solid (`Rwidget_set_material`), its colour painted as given. 14 headless and 16 real-window checks on 8.4 NTS and ZTS.

* **Update**: [UI primitives](/architecture/ui-primitives.md): homogeneous columns and rows as equal fill slots, which Raven shares by coefficient alone. 15 headless and 3 real-window checks on 8.4 NTS and ZTS.

* **Update**: [UI primitives](/architecture/ui-primitives.md): label runs through Raven's Markdown (bold and italic; a run's colour not shown; a quarter line taller per line), maximum size (a ceiling keeps the view out of a FILL slot and a filling view asks for the whole ceiling), and the icon after a button's label. 31 headless and 5 real-window checks on 8.4 NTS and ZTS.

* **Update**: [UI primitives](/architecture/ui-primitives.md): clickable groups through an event callback on each group's border (a press and release on it or on passive content inside post `GroupClicked`; controls keep theirs; a list's items now keep their primary clicks too); MOVE transitions not shown (Raven cannot move a widget by its size), the view shows and hides at once. 36 headless and 7 real-window checks on 8.4 NTS and ZTS.

* **Update**: [UI primitives](/architecture/ui-primitives.md): `RavenOverlay` (`R_WIDGET_OVERLAY` in a border; a child that fills both ways is capped to no desired size, so it never sizes the overlay), each corner's own radius on borders (an image's picture and a button take one radius, square when they differ), and clip to the rectangle (Raven has no rounded clip). 27 headless and 18 real-window checks on 8.4 NTS and ZTS.

## 2026-11-24

* **Correction**: [UI primitives](/architecture/ui-primitives.md): a borderless or link button showed no background at rest (Raven's flat button paints none); with a background it is now Raven's default button with its line taken away and the background as its control colours. Flat stays for no background. 20 headless and 10 real-window checks on 8.4 NTS and ZTS.

* **Update**: [UI primitives](/architecture/ui-primitives.md): topside's new settings where Raven shows them natively: per-side live padding, margin, cursor, tooltip and border on every primitive; the button's style, font, text colour, alignment and icon; the search, borderless, read-only and maximum-length field; the link colour. Margin in a fixed, the GRAB cursors and button roles are not shown (Raven has none). A flat button's background no longer brings its hover fill back; a field's background shows over its own fill. 101 headless and 19 real-window checks on 8.4 NTS and ZTS.

* **Manifest**: venusian/framework and venusian/topside required for development at `^0.11.0` (the Windowless suite boots both). Version stays 0.11.0, with ext-raven `^0.11.0`.
* **CI**: `.github/workflows/tests.yml` runs the Windowless suite on ubuntu-24.04 and macos-15, PHP 8.4 and 8.5, NTS and ZTS, on Raven's host platform with the null renderer (no display, no Vulkan loader). ext-raven is built from its `0.11.x` branch (Linux needs only the X11 headers) and venusian/topside resolved from its `0.11.x` checkout.
* **README**: house style (badges, about, learning, AI note, contributing, conduct, security, license); the reference moves to the docs.

## 2026-11-22

* **Creation**: [Menu bar](/architecture/menu-bar.md): `RavenMenuBar` on topside's `TKMenuBar`, with Raven's own API only. [Window](/architecture/window.md) reports activation to topside's menus and takes the bar first in its box; [Session](/architecture/session.md) answers Raven's own Quit with the shown bar's and shows the About dialog. 15 windowless checks on Raven's host platform pass on 8.4 NTS and ZTS (`phpunit.xml`, `tests/Windowless`).
* **Correction**: an image cleared with `setFile(null)` kept its picture (0 passed for no texture); it is `R_INVALID_ID`.
* **Update**: [UI primitives](/architecture/ui-primitives.md): list, collection and tree on Topside's TK classes, in the primitive map.
* **Update**: [UI primitives](/architecture/ui-primitives.md): table and text area on Topside's TK classes, in the primitive map.
* **Update**: [UI primitives](/architecture/ui-primitives.md): scroll view, tab view, split view and grid on Topside's TK classes, in the primitive map.
* **Update**: [UI primitives](/architecture/ui-primitives.md): separator, progress bar, spinner, slider, toggle button, segmented control and image on Topside's TK classes, in the primitive map.

## 2026-11-21

* **Update**: [UI primitives](/architecture/ui-primitives.md): `RavenComboBox` is Raven's editable combo box with no timer watching it; Return under completions submits; a text input takes its font's size. Needs the ext-raven nightly.
* **Correction**: [UI primitives](/architecture/ui-primitives.md): the text input's font size, the focus timer and Return under completions were recorded as Raven's limits; they are this adapter's. The close-time segfault is fixed upstream (`cb77806`).
* **Update**: [UI primitives](/architecture/ui-primitives.md): text input, combo box, date picker and link on Topside's TK classes; text styling moved from the label to `RavenText`. 30 real-window checks pass.
* **Update**: [UI primitives](/architecture/ui-primitives.md): check box, switch and dropdown on Topside's `TKCheckbox`, `TKToggle`, `TKDropdown`, in the primitive map. 11 real-window checks pass.
* **Creation**: [UI primitives](/architecture/ui-primitives.md): column, row, fixed, label and button on Topside's TK classes; the driver declares the primitive map and the window mounts the content column. 35 real-window harness checks pass. Commit d5650d6.
* **Initialization**: bundle created for the Surface toolkit adapter: [Session](/architecture/session.md) (`Raven`, `RavenSession`, `RavenAdapterDriver`), [Window](/architecture/window.md) (`RavenWindow` on Topside's `ToolkitWindow` lifecycle) and [Config](/api/config.md). The bridge-era code is under `deprecated2/` and has no concepts here. Commit `1ebc6c1`.
* Checked in a scratch topside-dev: open, resize from code, confirm-close and `close()` post the right mail. The provider is on `ToolkitAdapterServiceProvider` and builds no driver until `toolkits.default` asks for `raven`.
