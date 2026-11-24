---
type: Module
title: UI primitives
description: The Raven column, row, fixed, label, button, check box, switch and dropdown on Topside's TK classes, and the native widget each one is.
resource: src/UI/
tags: [raven, primitives]
status: draft
generated: { by: claude-opus/5.5, at: 2026-11-21T18:58:43Z }
sources:
  - id: native
    resource: src/UI/Concerns/RavenNative.php
    title: RavenNative
  - id: box
    resource: src/UI/Concerns/RavenBoxChildren.php
    title: RavenBoxChildren
---

# Classes

Each extends Topside's class (`TKColumn`, `TKRow`, `TKFixed`, `TKLabel`, `TKButton`, `TKCheckbox`, `TKToggle`, `TKDropdown`, `TKTextInput`, `TKComboBox`, `TKDatepicker`, `TKLink`, `TKSeparator`, `TKProgressBar`, `TKSpinner`, `TKSlider`, `TKToggleButton`, `TKSegmentedControl`, `TKImage`, `TKScrollView`, `TKTabView`, `TKSplitView`, `TKGrid`, `TKTable`, `TKTextArea`, `TKListView`, `TKCollectionView`, `TKTree`) and fills in its hooks; the driver's `$primitives` map names them. Fonts and alignment of what shows text are in `RavenText`. Shared native work is in `RavenNative`, what column and row share in `RavenBoxChildren`.[^native]

| Class | Native |
|---|---|
| `RavenColumn`, `RavenRow` | `R_WIDGET_VBOX` / `HBOX` inside a border; fill decides a child's share along the axis (`Rslot_set_fill` / `Rslot_set_auto`), alignment places it in its slot |
| `RavenFixed` | `R_WIDGET_CANVAS`: slot position and size, the larger of frame and minimum; its floor covers every frame (a canvas measures 0x0) |
| `RavenLabel` | `Rlabel_create`, left-aligned (Raven's default); fonts by file, LIGHT/MEDIUM draw regular, SEMIBOLD/BOLD/BLACK bold; a missing family falls back to the default font |
| `RavenButton` | `Rbutton_create`; clicked callback held weakly, cleared on release; background on the control colour roles, radius on `R_METRIC_CORNER_RADIUS` |
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
| `RavenGrid` | `R_WIDGET_GRID`, fill per row and column |
| `RavenTable` | `R_WIDGET_TABLE`, cells set one by one |
| `RavenTextArea` | Raven's text editor; refuses other alignment than left, line and range styles, and scroll offsets (it has `scrollToLine()`) |
| `RavenListView` | a scroll box with a widget per row (`RavenItemView`): not virtual |
| `RavenCollectionView` | the same over a wrap box; a double click activates |
| `RavenTree` | `R_WIDGET_TREE_VIEW`; one text per row; a timer sees opening and closing (no callback) |

# Notes

* Raven's "none" is `R_INVALID_ID` (4294967295), never 0: 0 is refused, as a texture (the picture stays) and as a tree item's parent (no item is made).
* Resizing the window holds the pump until release on macOS (measured by hand 2026-11-22: 3.0 s with no pump return), so timers and mail wait; a repeating timer then fires once and skips the beats it missed.
* During a resize the window stretches its last frame and snaps at release (seen by hand). By reading Raven (`fc37a06`, upstream main that day): `cocoa_platform.m` emits `RVN_PEVENT_MODAL_TICK`, which is what draws inside a modal loop, only in `windowDidEndLiveResize`; Win32 emits it on a timer throughout. Not reproduced in C yet.
* A drag does not hold the pump (measured 2026-11-22, headless, injected: about 240 pump returns and all 8 steps heard with the button down, slider and divider).
* Two Rids per primitive: the native the parent holds (an `R_WIDGET_BORDER` where a background, corner radius or padding is needed) and the control that takes focus and input.
* Transitions: Raven's own appear transitions (`FADE`, `SLIDE_LEFT`/`RIGHT`, `FLY_UP`/`DOWN` for `SLIDE_UP`/`DOWN`). Raven has no disappear transition, so hiding is immediate, and its springs take no duration.
* `hasFocus()`: the control is its window's focus (`Rwidget_is_focused`). Enabled: Raven keeps what is inside a disabled widget from input itself.

# Checked

List, collection and tree (2026-11-22, a scratch harness, NTS and ZTS, and by screenshot): code changes post nothing; select, open, close and activate post; disabled items post nothing; removal. All sixteen primitives of that day's three batches also render from one Sharp file.

Table and text area (2026-11-22, a scratch harness, NTS and ZTS): fill, focus, code changes post nothing, the user's selection and typing post, line selection, scroll to a line, removal.

Scroll view, tab view, split view and grid (2026-11-22, a scratch harness, real windows and headless, NTS and ZTS): sizes, fill through a plain row, code changes post nothing, tab choice, an injected divider drag, removal. Slider and divider dragged by hand: live.

Separator, progress bar, spinner, slider, toggle button, segmented control and image (2026-11-22, a scratch harness, 25 checks in a real window on NTS and ZTS): sizes in a column and a row; code changes post nothing; the user's slide, press and choice post their mail; disabled posts nothing; PNG and SVG show, another file is refused; removal. Looked at in a screenshot.

Text input, combo box, date picker and link (2026-11-21, a scratch real-window harness, 30 checks, active and inactive window): code changes post nothing; typing, Return, option, completion and pick post one mail each; completions keep focus and hide on Esc, choice and focus leaving; disabled posts nothing; removal. The link is not clicked. On ext-raven nightly `bd404e8` (installed 2026-11-21, NTS and ZTS): 41 headless checks, the 30 in real windows, and the close-time segfault of older Raven builds reproduced in C as gone.

Check box, switch and dropdown (2026-11-21, a scratch real-window harness, 11 checks): changes from code post nothing; the user's toggle, flip and choice each post one mail with the new value; new options with a selection post nothing; nothing from inside a disabled column; removal. Driven by input injected into the window: a mouse click on the check box and switch, Up on the focused combo box.

The real-window harness (a scratch topside-dev, 35 checks) through these classes passes: building, natives inside their containers, fixed frames, label text, alignment and font, a native click posting `ButtonClicked`, the enabled cascade, `focus()` moving between two buttons with `hasFocus()`, reordering, transitions, text colour, removal, close. Commit d5650d6.

[^native]: RavenNative
