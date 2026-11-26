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

Raven reports neither activation nor a scale change, so `settle()` compares both after each pump: `WindowFocused` once per activation and `WindowScaleChanged` when `Rwindow_get_scale()` moved, from the first show on.

# Close

* `closeNative()` = `Rwindow_close()`: Raven runs the close callback, which takes the close path.
* `beforeClose()` closes the owned children first: Raven destroys a window's owned windows with it without asking them.

# Alert

`showAlert()`: `Rdialog_open` with content laid out as Raven's own `Rmessage_box` (whose buttons are fixed sets): a 380-wide box at spacing 12, the message at 17, the detail wrapped at 0.8 opacity, a Raven check box for the suppression, the buttons in order in a right-aligned row (spacing 8, top padding 6, at least 84 wide), the default one Primary. A button closes the dialog with its index plus one; Enter on the content closes it with the default; Esc is Raven's dismissal, 0, the cancel button. No style or title. `closeAlert()` closes the dialog before the window goes. Checked by a real-window script against the live repos (18 checks).

# Browse

`openUrl()`: `Ropen_url()`.

# Not ported yet

From `deprecated2/RavenWindow.php`: right-click and focus tracking, the content floor.

[^window]: RavenWindow
