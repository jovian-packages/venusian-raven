---
type: Module
title: Window
description: A Raven window with a vbox content widget, wired to Topside's window lifecycle; activation and scale compared after each pump, owned child windows block their parent's input.
resource: src/RavenWindow.php
tags: [raven, windows]
status: draft
generated: { by: claude-opus/5.5, at: 2026-11-21T17:39:27Z }
verified:
  - { by: claude-opus/5.5, at: 2026-11-20T21:20:00Z }
sources:
  - id: window
    resource: src/RavenWindow.php
    title: RavenWindow
---

# Overview

`RavenWindow extends Surface\Toolkits\ToolkitWindow`. The close path, confirm-close, `close()` from code, resize mail and the after-pump `settle()` call are Topside's; this class builds the Raven window and connects its callbacks to them.[^window]

# Native

* `Rwindow_create(name, width, height, R_WINDOW_HIDDEN)`; a failure throws with `Rerror_get_last()`. Hidden until `present()`.
* Content: an `R_WIDGET_VBOX` set with `Rwindow_set_content` (the window owns and destroys it).
* A child window: `Rwindow_set_owner(parent)`; it stays above the parent and goes with it.
* `Rwindow_center`. `native()` = the window's Rid; `size()` = `Rwindow_get_size()`, in logical units.

# Callbacks

Each holds the window weakly (a strong capture would be a cycle through ext-raven's held slots).

| Raven | Goes to |
|---|---|
| close callback | `allowsUserClose()`: `R_FALSE` keeps the window while confirming; else the close path, and `R_TRUE` lets Raven destroy it |
| resize callback | `resized()`: one `WindowResized` per pump; then the scale check |
| event hook | while an owned child is open, the parent's input is kept from its widgets; a mouse press brings the child forward (Raven has no modal window) |

Raven reports neither activation, a scale change nor a widget's size, so `settle()` compares them after each pump (a truncating label is cut again, each view that watches its size is asked): `WindowFocused` once per activation and `WindowScaleChanged` when `Rwindow_get_scale()` moved, from the first show on.

# Close

* `closeNative()` = `Rwindow_close()`: Raven runs the close callback, which takes the close path.
* `beforeClose()` closes the owned children first: Raven destroys a window's owned windows with it without asking them.

# Alert

`showAlert()`: `Rdialog_open` with content laid out as Raven's own `Rmessage_box` (whose buttons are fixed sets): a 380-wide box at spacing 12, the message at 17, the detail wrapped at 0.8 opacity, a Raven check box for the suppression, the buttons in order in a right-aligned row (spacing 8, top padding 6, at least 84 wide), the default one Primary. A button closes the dialog with its index plus one; Enter on the content closes it with the default; Esc is Raven's dismissal, 0, the cancel button. No style or title. `closeAlert()` closes the dialog before the window goes. Checked by a real-window script against the live repos (18 checks).

# Save panel

`showSave()`: `Rfile_dialog_save(RFileDialogDesc)` with the window as `parent`, the request's title, folder and suggested name (`file_name`). Raven shows the system's panel on Windows, and on Linux through `zenity` or `kdialog` when one is installed and `DISPLAY` or `WAYLAND_DISPLAY` is set (dialogs.c); otherwise (macOS, no tool, the null renderer) its own file browser in a dialog over the window; either asks before replacing a file. The call waits for the answer in a loop of its own, so `showSave()` posts it with `Rtask_post()`: the panel opens in the next frame and `saveFile()` returns at once, as the retired `RavenDialogs` did. The task checks a flag first: `closeSave()` clears it, so a panel not shown yet is never shown, and a window closed with the panel open ends the panel's loop (Raven destroys the window) and its late return posts nothing (`saveAnswered()` finds no open panel). The path chosen, or null for a cancel, goes to `saveAnswered()`. `tests/Windowless/SaveTest.php` (5 cases, headless): a timer inside the panel's loop reads the dialog (the `Rwindow_get_popup()` root: its title label, the browser's `Rfile_browser_get_directory()`, the name field's `Rtext_input_get_text()`) and answers it with Esc, or a mouse press (`Rhost_inject_mouse_button`, in pixels: a rect is in logical units) on its Save button.

# Browse

`openUrl()`: `Ropen_url()`.

# Not ported yet

From `deprecated2/RavenWindow.php`: right-click and focus tracking, the content floor.

[^window]: RavenWindow
