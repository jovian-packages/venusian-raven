---
type: Module
title: Session
description: Raven started once per process, its quit handed to the app, and a pump that is one frame wait, ended by the loop's descriptor through Cocoa on macOS or in slices elsewhere.
resource: src/Raven.php
tags: [raven, session, macos, linux]
status: draft
generated: { by: claude-opus/5.5, at: 2026-11-26T09:00:00Z }
sources:
  - id: raven
    resource: src/Raven.php
    title: Raven (the Toolkit)
  - id: session
    resource: src/RavenSession.php
    title: RavenSession
  - id: driver
    resource: src/RavenAdapterDriver.php
    title: RavenAdapterDriver
---

# Overview

Three classes, on Topside's toolkit layer:

| Class | Extends / implements | Job |
|---|---|---|
| `RavenAdapterDriver` | `Surface\Toolkits\Drivers\ToolkitAdapterDriver` | refuses without ext-raven; makes the session from [config](/api/config.md); declares `RavenWindow` as its window class |
| `RavenSession` | `Surface\Toolkits\ToolkitSession` | builds the `Raven` toolkit once; Raven's quit posts `QuitRequested` |
| `Raven` | `Surface\Contracts\Toolkits\Toolkit` | the Raven calls below |

The window list and the after-pump settle are Topside's (`ToolkitSession`).[^session]

# Calls

| When | Raven calls |
|---|---|
| built (once per process) | `Rinit_ex(RInitDesc)` unless `Ris_initialized()`: backend (`vulkan` or `null`; headless forces null), `app_name`, headless, `ui_scale`; a failure throws with `Rerror_get_last()`. Then the theme: a built-in name, or a theme XML loaded with `Rtheme_load()`, set with `Rtheme_use()` |
| `connect()` | `Raction_bind('app.quit', ...)`: Raven's quit (macOS app menu, Cmd+Q, the Dock) calls the app's handler instead of stopping Raven |
| `disconnect()` | `Raction_bind('app.quit', null)`: back to Raven's own |
| `pump(0)` | one `Rdraw_frame()` |
| `pump($ns)` | macOS with ext-appkit: one `Rwait_frame(budget)`. Elsewhere: `Rwait_frame()` in slices of at most `raven.wait_slice_ms`, checking the loop's descriptor between them |

[^raven]

# Wake

Raven watches none of the application's descriptors.[^raven]

* **macOS, not headless, ext-appkit loaded**: Raven waits in Cocoa's run loop. The loop's descriptor joins it as a `CFFileDescriptor` whose callout calls `Rwake_up()`; the callout is one-shot and re-armed by each pump.
* **Elsewhere** (X11, Wayland, the headless null platform): the descriptor is reopened as a non-blocking stream, and a sliced pump returns when `stream_select()` finds it readable, when Raven returned before its slice ran out (it had work), or when the budget is spent. A loop wake waits at most one slice.

[^raven]: Raven (the Toolkit)
[^session]: RavenSession

## Colour scheme

`RavenSession::applyColorScheme()` (topside's once-only hook, after `Raven` has applied `raven.theme`) asks `Raven::applyColorScheme()`: dark or light is the member of the theme's family with the trailing `Dark`/`Light` swapped (`GlassDark`/`GlassLight`, `Dark`/`Light`, `HighContrastDark`/`HighContrastLight`, the looks too), by `Rtheme_use`; a theme already of the scheme is kept, system changes nothing, and a theme with no such member throws.
