---
type: Module
title: Menu bar
description: One Raven MenuBar per window, first in its box - how nodes become Raven menu widgets, how Raven's macOS application menu is answered, and why some shortcuts show as text.
resource: src/Menus/
tags: [raven, menus, macos, linux]
status: draft
generated: { by: claude-opus/5.5, at: 2026-11-26T09:00:00Z }
sources:
  - id: bar
    resource: src/Menus/RavenMenuBar.php
    title: RavenMenuBar
  - id: shortcut
    resource: src/Menus/RavenShortcut.php
    title: RavenShortcut
  - id: tests
    resource: tests/Windowless/MenuBarTest.php
    title: Windowless menu tests
  - id: raven
    resource: php-io-extensions/raven/lib/raven/src/widgets/menu.c
    title: Raven 0.3.0 menu widgets and the macOS system bar (menu.c, widget/window.c, platform/cocoa/cocoa_platform.m)
---

# Overview

`RavenMenuBar` is topside's `TKMenuBar` on Raven: it is told single operations and keeps no rule of the menus. The driver names it. Only Raven's own API is used.[^bar]

A window's bar is a `R_WIDGET_MENU_BAR` put first in the window's box by `RavenWindow::attachMenuBar()`. Raven shows it: drawn above the content on Linux; on macOS given to the system bar while the window is the active one, after Raven's own application menu, and Raven gives the system bar the whole tree again whenever its signature changes (`rvn_menu_bar_sync`). The bar for no window is built and never shown: with no window active Raven shows its own application menu only.

`RavenWindow::settle()` reports activation to topside: `gainedFocus()` when the window becomes active, `lostFocus()` when it stops.

# Natives

Menus and items are widgets. Raven's menu calls only append, so each new widget is detached (`Rwidget_remove_child`) and put at its place with `Rwidget_insert_child`; a move is the same, a removal is `Rwidget_destroy`.

| What | How |
|---|---|
| A menu | `Rmenu_add_menu`; label `Rmenu_set_title` |
| A line | `Rmenu_add_separator`; none in the bar itself |
| An item | `Rmenu_add_item`; its clicked callback calls `activated(uuid)` |
| A toggle | a checkable item. Raven flips it before the callback: the mark is put back, and topside sets it through `change()` |
| A choice | one checkable item per option |
| Enabled | `Rwidget_set_enabled` |
| Edit kinds | the edit chord sent into the window (`Rwindow_inject_key`): Raven's text widgets edit by their keys; Paste and Match Style is Paste's chord, as Raven's text edit pastes plain text only |
| Close Window, Minimize | `Rwindow_close` (through the close callback and its confirm), `Rwindow_minimize` |
| Full screen | `supports()` is false: Raven has no call for it. Topside shows it disabled and logs once |
| About panel | `RavenSession::showAbout()`: Raven has no About panel, so a dialog (`Rdialog_open`) with the fields, one at a time |

# The macOS application menu

Raven builds it itself (`rvn_cocoa_app_menu_item`): About (AppKit's standard panel with the process's own details), Hide, Hide Others, Show All, Quit. Raven has no call to change it.

* Its Quit runs `app.quit`, which the session binds: the shown bar's Quit node answers it (`RavenMenuBar::quitRequested()`), else the session posts `QuitRequested`. A Quit in the application's own menu has no widget.
* Hide, Hide Others and Show All there have no widget: Raven's are always there. Written elsewhere, they are shown disabled.
* Every other node of the application's own menu, About included, goes in an ordinary menu under the application's name, after Raven's. It is collapsed (`R_COLLAPSED`, which Raven leaves out of the system bar) while it holds nothing.

# Shortcuts

Raven gives a key to the top popup, then the focused widget, then the window's shortcuts, then the menus' (`rvn_window_key`). On macOS Raven gives the system bar each item's key as an AppKit key equivalent, which AppKit runs before the window sees the key; an item with shortcut text gets none, and Raven still runs its bound shortcut in its own order.

| Shortcut | How |
|---|---|
| with Primary, Ctrl or Alt | `Rmenu_item_set_shortcut` |
| none of the three, on macOS | `Rmenu_item_set_shortcut` and the text macOS writes it with (`Rmenu_item_set_shortcut_text`) |
| an edit kind's | text only, never bound: the focused widget's own keys do the work, and an injected chord the widget did not use cannot come back to the item |

`RavenShortcut`: Primary is `R_MOD_SUPER` (Command) on macOS and `R_MOD_CTRL` elsewhere.

Measured 2026-11-22, Raven 0.3.0, 8.4 NTS and ZTS, on Raven's host platform (`Rhost_inject_key`): a focused text input keeps a bare Delete, and with no focus the menu's item runs. Not measured: the macOS system bar, which needs an active window.

[^bar]: RavenMenuBar
