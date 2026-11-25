---
type: Module
title: UI primitives
description: The Raven primitives on Topside's TK classes, the native widget each one is, and which of Topside's settings Raven shows natively.
resource: src/UI/
tags: [raven, primitives]
status: draft
generated: { by: claude-opus/5.5, at: 2026-11-25T15:47:18Z }
sources:
  - id: native
    resource: src/UI/Concerns/RavenNative.php
    title: RavenNative
  - id: box
    resource: src/UI/Concerns/RavenBoxChildren.php
    title: RavenBoxChildren
  - id: surface
    resource: src/UI/Concerns/RavenButtonSurface.php
    title: RavenButtonSurface
---

# Classes

Each extends Topside's class (`TKColumn`, `TKRow`, `TKOverlay`, `TKFixed`, `TKLabel`, `TKButton`, `TKCheckbox`, `TKToggle`, `TKDropdown`, `TKTextInput`, `TKComboBox`, `TKDatepicker`, `TKLink`, `TKSeparator`, `TKProgressBar`, `TKSpinner`, `TKSlider`, `TKToggleButton`, `TKSegmentedControl`, `TKImage`, `TKScrollView`, `TKTabView`, `TKSplitView`, `TKGrid`, `TKTable`, `TKTextArea`, `TKListView`, `TKCollectionView`, `TKTree`) and fills in its hooks; the driver's `$primitives` map names them. Fonts and alignment of what shows text are in `RavenText`. Shared native work is in `RavenNative`, what column and row share in `RavenBoxChildren`.[^native]

| Class | Native |
|---|---|
| `RavenColumn`, `RavenRow` | `R_WIDGET_VBOX` / `HBOX` inside a border, whose padding (each side its own, live) is theirs; fill decides a child's share along the axis (`Rslot_set_fill` / `Rslot_set_auto`), alignment places it in its slot |
| `RavenOverlay` | `R_WIDGET_OVERLAY` in a border, whose padding (each side its own, live) is the overlay's. Raven draws children in order, the first at the back; a reorder is `Rwidget_insert_child`, focus kept. Each child is placed in the whole area by its slot: alignment, margin as slot padding, and FILL on an axis it fills. Raven's overlay asks for its largest child, so a child that fills both ways has its native's desired size capped at 0 (`Rwidget_set_max_size`; -1 lifts it): the FILL slot still stretches it over the area, so it takes the overlay's size and never gives one, its minimum size included |
| `RavenFixed` | `R_WIDGET_CANVAS`: slot position and size, the larger of frame and minimum; its floor covers every frame (a canvas measures 0x0) |
| `RavenLabel` | `Rlabel_create`, left-aligned (Raven's default); fonts by file, LIGHT/MEDIUM draw regular, SEMIBOLD/BOLD/BLACK bold; a missing family falls back to the default font |
| `RavenButton` | `Rbutton_create`; clicked callback held weakly, cleared on release; background on the control colour roles, radius on `R_METRIC_CORNER_RADIUS`; with an icon its one child is an HBox of an image and Raven's label |
| `RavenCheckbox` | `Rcheckbox_create` in a border; the changed callback reports the new state, cleared on release |
| `RavenToggle` | `Rcheckbox_create` with `R_CHECKBOX_SWITCH`, no text, in a border; the changed callback reports the new state |
| `RavenDropdown` | `R_WIDGET_COMBO_BOX` in a border; the changed callback reports the choice, ignored while items are replaced. `Rcombo_box_set_selected()` from code reports nothing |
| `RavenTextInput` | text input in a border; a font's size is the input's `R_METRIC_FONT_SIZE` (its height stays the theme's control height); left alignment only (Raven paints from the left, another is refused) |
| `RavenComboBox` | editable `R_WIDGET_COMBO_BOX` in a border, the field as control (never set the field's event callback: it is the combo's). A selection is a choice unless the text callback follows before the next frame (typing an option exactly). Completions are a menu under `Rpopup_open`, whose closed callback clears them; `RavenWindow::watchInput()` (Raven has one hook per window) lets Return with none highlighted submit. Raven closes popups when the window loses the keyboard. Needs ext-raven's 0.11.x-nightly of 2026-11-19 or later |
| `RavenDatepicker` | `R_WIDGET_DATE_PICKER` in a border |
| `RavenLink` | unwrapped Markdown label, opened with `Ropen_url` |
| `RavenSeparator` | `R_WIDGET_SEPARATOR` |
| `RavenProgressBar` | `R_WIDGET_PROGRESSBAR`, centred down its slot; a Raven timer sweeps it while indeterminate (Raven has no such mode) |
| `RavenSpinner` | `R_WIDGET_CUSTOM` painting a ring of dots |
| `RavenSlider` | `R_WIDGET_SLIDER` |
| `RavenToggleButton` | a button in the primary style while pressed; its surface is `RavenButtonSurface`, shared with the button |
| `RavenSegmentedControl` | a tab view with empty pages (Raven has none) |
| `RavenImage` | `R_WIDGET_IMAGE` with a texture of its own. Its height is the picture's natural one: Raven's layout cannot make height follow width (a size box's ratio only arranges) |
| `RavenScrollView` | `R_WIDGET_SCROLL_BOX` |
| `RavenTabView` | `R_WIDGET_TAB_VIEW`, titles on the slots |
| `RavenSplitView` | `R_WIDGET_SPLITTER`, shares as slot fill; no callback, so a Raven timer compares while the mouse is down |
| `RavenGrid` | `R_WIDGET_GRID` in a border, whose padding is the grid's; fill per row and column |
| `RavenTable` | `R_WIDGET_TABLE`, cells set one by one |
| `RavenTextArea` | Raven's text editor; refuses other alignment than left, line and range styles, and scroll offsets (it has `scrollToLine()`) |
| `RavenListView` | a scroll box with a widget per row (`RavenItemView`): not virtual |
| `RavenCollectionView` | the same over a wrap box; a double click activates |
| `RavenTree` | `R_WIDGET_TREE_VIEW`; one text per row; a timer sees opening and closing (no callback) |

# Settings Raven shows, and the ones it leaves unshown

Topside's optional hooks are overridden only where Raven has a native way; elsewhere the hook stays empty and the setting is not shown (the getter still answers). Nothing is drawn or composed in its place.[^native][^surface]

| Setting | On Raven |
|---|---|
| Padding (column, row, grid, tab page) | `Rborder_set_padding` on the wrapping border, each side its own, live |
| Margin | `Rslot_set_padding` on the native: boxes, grid, scroll box, tab view, splitter and the window's box keep it. **Not shown in a fixed**: Raven's canvas places by frame and reads no slot padding |
| Cursor | `Rwidget_set_cursor` on the native; also on a control that sets its own (text input, text area, combo box field: the text beam), which Raven would find first (the text editor's find bar sets its own while it is open). Null is 0 on the native (Raven's "none of its own", what its own widgets use) and the text beam on such a control. **GRAB, GRABBING not shown**: Raven has no such cursor. Raven looks from the widget under the pointer up through its parents, so a container's cursor reaches what has none. Over a link run Raven's label sets its own hand. On Cocoa Raven draws MOVE as the open hand and the two diagonal resizes as the arrow |
| Tooltip | `Rwidget_set_tooltip` on the native; Raven shows the nearest one up from the widget under the pointer |
| Border | Wrapped: `Rborder_set_outline` (drawn on the border's edge, taking no room); a null colour is `R_COLOR_BORDER` as Raven resolves it for the border. Button and toggle button: `R_METRIC_BORDER_WIDTH` and `R_COLOR_CONTROL_BORDER` on the button; width 0 gives the theme's own button line back (as a field keeps its frame). Raven's own look draws no line round a flat or primary button, and the platform looks draw their line at their own width |
| Button style | DEFAULT `R_BUTTON_DEFAULT`. BORDERLESS and LINK have no line. With no background they are `R_BUTTON_FLAT` with `R_COLOR_CONTROL_HOVERED` / `_PRESSED` at alpha 0: no fill in any state on Raven's own look. With a background they are `R_BUTTON_DEFAULT` with `R_METRIC_BORDER_WIDTH` 0 and `R_COLOR_CONTROL_BORDER` at alpha 0, the control colours the background and its hover and pressed shades: a flat button paints nothing at rest, so it cannot show one. A border set on them comes back with DEFAULT. On the platform looks a flat button still shows Raven's pressed ink (from the text colour, not a role), on glass themes its glass while used, and Aqua's push button its fixed top or bottom edge shade. LINK's text is the button's `R_COLOR_LINK` (read with `Rwidget_get_color`) on its `R_COLOR_TEXT`, unless a text colour is set; topside gives it the pointing hand |
| Button font, colour, alignment | `Rwidget_set_font` on the button, the size `Rlabel_set_text_size` on Raven's label; `R_COLOR_TEXT` on the button, which the label inherits; the content's slot `Rslot_set_halign` and the label's `Rlabel_set_text_align` (centred, as Raven makes it, until set) |
| Button icon | One child, as Raven's header puts it ("e.g. an HBox with an icon"): an HBox of an `R_WIDGET_IMAGE` and Raven's own label moved into it, the theme's `R_METRIC_SPACING` between. A file is a texture of the button's own (`Rtexture_create_from_file`, refused when unreadable); a symbol is `Ricon_get` (Lucide, in the text colour); a name Raven ships no icon for shows none. Square, 1.25 times the text size. The label is set with `Rlabel_set_text`: `Rbutton_set_text` would put a second label in place of the box |
| Corner radius | Each corner its own on the border (`Rborder_set_corner_radius` takes four). An image's border takes four; its picture rounds with one (`Rimage_set_corner_radius`): the radius when the corners agree, **square when they differ**. A button rounds with one metric (`R_METRIC_CORNER_RADIUS`): agreeing corners take it (0 the theme's), **differing corners are square** |
| Clip | `Rwidget_set_clipping` on the native: Raven clips descendants to the widget's rectangle. **A rounded clip is not shown**: Raven has no way to clip to a radius, so corners outside the radius still show what is inside (a picture filling a rounded overlay stays square there) |
| Clickable group | Every group (column, row, grid, overlay, fixed, scroll view, split view, tab view and its pages): an event callback on the native border (`Rwidget_set_event_callback`). Raven sends a press and a release to the hit-testable widget under the pointer and bubbles them up, each widget seeing the point from its own top-left; labels, pictures and boxes let the mouse through to the border round them, which passes it on. A primary press reaching the group's border is the group's (consumed, so a clickable group above does not take it too); the release that follows on it posts `GroupClicked` at the point from the group's top-left. The window's input watcher forgets a press at the next one anywhere, so press elsewhere and release here clicks nothing. A button, field, link or slider keeps its own click (Raven's control consumes it); a list's or collection's items now consume their primary press and release too (they are the view's). Raven delivers nothing to a disabled widget; topside checks the containers above. Off and on release the callback and the watcher go |
| MOVE transitions | **Not shown**: Raven has no public way to move a widget, no transition distance and no closing transition; its slide is 28 points with a fade, which is not a move by the view's own size. A view given MOVE_RIGHT, MOVE_LEFT, MOVE_TOP or MOVE_BOTTOM shows and hides at once. FADE and SLIDE_* are unchanged. A hidden view is collapsed, so it takes no clicks |
| Button role | **Not shown**: Raven has no default or cancel button |
| Search field | `R_TEXT_INPUT_TYPE_SEARCH`, Raven's search type, which picks the on-screen keyboard; Raven has no search field look, so nothing more is drawn |
| Borderless field | `R_COLOR_INPUT`, `R_COLOR_BORDER` and `R_COLOR_CONTROL_BORDER` at alpha 0, `R_METRIC_BORDER_WIDTH` 0 on the field; false clears them |
| Field background | The border paints it; the field's `R_COLOR_INPUT` is clear while one is set, so it shows |
| Read-only, maximum length | `Rtext_input_set_read_only`; `Rtext_input_set_max_length` (0 is none) |
| Link colour | The label's own `R_COLOR_LINK`, which Raven draws link runs in (`Rlabel_set_color` colours the other text); null clears it |

# Notes

* Raven's "none" is `R_INVALID_ID` (4294967295), never 0: 0 is refused, as a texture (the picture stays) and as a tree item's parent (no item is made).
* Resizing the window holds the pump until release on macOS (measured by hand 2026-11-22: 3.0 s with no pump return), so timers and mail wait; a repeating timer then fires once and skips the beats it missed.
* During a resize the window stretches its last frame and snaps at release (seen by hand). By reading Raven (`fc37a06`, upstream main that day): `cocoa_platform.m` emits `RVN_PEVENT_MODAL_TICK`, which is what draws inside a modal loop, only in `windowDidEndLiveResize`; Win32 emits it on a timer throughout. Not reproduced in C yet.
* A drag does not hold the pump (measured 2026-11-22, headless, injected: about 240 pump returns and all 8 steps heard with the button down, slider and divider).
* Two Rids per primitive: the native the parent holds (an `R_WIDGET_BORDER` where a background, corner radius or padding is needed) and the control that takes focus and input.
* Transitions: Raven's own appear transitions (`FADE`, `SLIDE_LEFT`/`RIGHT`, `FLY_UP`/`DOWN` for `SLIDE_UP`/`DOWN`). Raven has no disappear transition, so hiding is immediate, and its springs take no duration.
* `hasFocus()`: the control is its window's focus (`Rwidget_is_focused`). Enabled: Raven keeps what is inside a disabled widget from input itself.

# Checked

Clickable groups and MOVE transitions (2026-11-25, a scratch workbench on topside `22f5126`, 8.4.27 NTS and ZTS): 36 headless checks on Raven's host platform, clicks injected as an OS would: a clickable column posts for a click on itself, a label and a picture in it, with the point from its top-left; a button inside posts only its own mail, a field none; a plain column inside passes the click on, a clickable one takes it alone; a right click, a press here released elsewhere and a press elsewhere released here click nothing; disabled, a disabled container above and off post nothing; an overlay, row, grid, fixed, scroll view, split view, tab view and tab page each post for a click on a label inside; a list's row in a clickable column is the list's (a double click activates, no group click); a link inside opens its URL and clicks no group; MOVE_* show and hide at once; a hidden full-size overlay child lets the click reach the button under it; removal while clickable. 7 checks in a real Cocoa window through Raven's own input (`Rtest_click_at`) and its pixels: the card's label click with its point, its button's own click, MOVE_RIGHT whole on the first frame and gone on the first frame while FADE is still fading on its first. Looked at in a screenshot. The Windowless suite: 15 pass on both builds.

Overlay, corners and clip (2026-11-25, a scratch workbench on topside `c6a57e2`, 8.4.27 NTS and ZTS): 27 headless checks reading Raven back: the overlay's natives, per-side live padding, depth order through moveTo and moveBefore, END/END and CENTER placing, margin, fill across winning over alignment, a 3200 x 2000 picture filling an overlay of minSize 0 320 in a scroll view leaving it 320 high and as wide (also with a 900 x 900 minimum on the picture), filling one way sizing it again, the window's size kept, a button's one radius and square differing corners, removal. 18 checks on the pixels of two real Cocoa windows: the picture covers (left red, right blue) and is cropped at the overlay's edges, the words at the bottom left over it, the hero 320 high and the window unchanged; a column's corners `0 0 24 24` square on top and round below; an image's picture rounded with one radius and square with differing ones; a clipped overlay cutting a framed square at its edge and an unclipped one letting it run on. Looked at in screenshots. The Windowless suite: 15 pass on both builds.

Seen there, not this adapter's to change: an overlay whose child fills both ways expands in its column (topside's `expands()` counts any filling child), and Raven's boxes share the room left to filling slots by coefficient alone, below a slot's minimum when several fill. In a plain column with spare room the hero took a share, not 320; in a scroll view whose content is taller than it, 320.

A borderless or link button with a background (2026-11-24, a scratch workbench, 8.4.27 NTS and ZTS): 20 headless checks reading the button's colours and metrics back through LINK and BORDERLESS with and without a background, a border set meanwhile, DEFAULT and a cleared background; 10 checks on the pixels of a real Cocoa window: a link row filled with `#FFFFFF1F` over the window, no line at its edges, its text and icon in the link colour, a row with no background showing nothing at rest, a borderless one in its own fill and text colour, and the cleared background and DEFAULT showing Raven's own again. One NTS run of five saw DEFAULT's colours still easing at the 0.6 s check; with 1.2 s all runs passed. Looked at in a screenshot. The Windowless suite: 15 pass on both builds.

Settings (2026-11-24, a scratch workbench, ext-raven 0.11.0 nightly `bd404e8` (Raven `fc37a06`), PHP 8.4.27 NTS and ZTS): 101 headless checks on Raven's host platform with the null renderer, reading Raven back: border and slot geometry for padding (column, row, grid, tab page; per side, live) and margin (column, row, grid, tab page; none in a fixed), the cursor Raven hands the platform for every shape and for null (button, label, field, link, a column over what is inside it), tooltips, the button's colour and metric overrides through each style and back, font, alignment, icon by symbol and file (texture freed, one label, a click on the icon clicks), the field's input type, borderless and background colours, read-only and maximum length by typing. 19 checks in a real Cocoa window on each build: the window's own pixels (outline, padding, no fill on the link-style button and the borderless field, the link colour, the margin showing the container's background) and AppKit's current cursor; one ZTS run of three saw AppKit's cursor reset under two hover checks, the other runs passed all 19. Looked at in a screenshot. The Windowless suite: 15 pass on both builds.

List, collection and tree (2026-11-22, a scratch harness, NTS and ZTS, and by screenshot): code changes post nothing; select, open, close and activate post; disabled items post nothing; removal. All sixteen primitives of that day's three batches also render from one Sharp file.

Table and text area (2026-11-22, a scratch harness, NTS and ZTS): fill, focus, code changes post nothing, the user's selection and typing post, line selection, scroll to a line, removal.

Scroll view, tab view, split view and grid (2026-11-22, a scratch harness, real windows and headless, NTS and ZTS): sizes, fill through a plain row, code changes post nothing, tab choice, an injected divider drag, removal. Slider and divider dragged by hand: live.

Separator, progress bar, spinner, slider, toggle button, segmented control and image (2026-11-22, a scratch harness, 25 checks in a real window on NTS and ZTS): sizes in a column and a row; code changes post nothing; the user's slide, press and choice post their mail; disabled posts nothing; PNG and SVG show, another file is refused; removal. Looked at in a screenshot.

Text input, combo box, date picker and link (2026-11-21, a scratch real-window harness, 30 checks, active and inactive window): code changes post nothing; typing, Return, option, completion and pick post one mail each; completions keep focus and hide on Esc, choice and focus leaving; disabled posts nothing; removal. The link is not clicked. On ext-raven nightly `bd404e8` (installed 2026-11-21, NTS and ZTS): 41 headless checks, the 30 in real windows, and the close-time segfault of older Raven builds reproduced in C as gone.

Check box, switch and dropdown (2026-11-21, a scratch real-window harness, 11 checks): changes from code post nothing; the user's toggle, flip and choice each post one mail with the new value; new options with a selection post nothing; nothing from inside a disabled column; removal. Driven by input injected into the window: a mouse click on the check box and switch, Up on the focused combo box.

The real-window harness (a scratch topside-dev, 35 checks) through these classes passes: building, natives inside their containers, fixed frames, label text, alignment and font, a native click posting `ButtonClicked`, the enabled cascade, `focus()` moving between two buttons with `hasFocus()`, reordering, transitions, text colour, removal, close. Commit d5650d6.

[^native]: RavenNative
[^surface]: RavenButtonSurface
