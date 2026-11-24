---
type: Reference
title: Config
description: config/raven.php keys read by the driver and the toolkit.
resource: config/raven.php
tags: [raven, config]
status: draft
generated: { by: claude-opus/5.5, at: 2026-11-21T17:39:27Z }
sources:
  - id: config
    resource: config/raven.php
    title: config/raven.php
  - id: driver
    resource: src/RavenAdapterDriver.php
    title: RavenAdapterDriver
---

# Schema

Merged under `raven` by `VenusianRavenServiceProvider` and published as `config/raven.php` (tag `raven-config`). The toolkit is chosen by Topside's `toolkits.default` (`TOOLKIT_DRIVER=raven`).[^config]

| Key | Default | Meaning |
|---|---|---|
| `raven.application_name` | `env('APP_NAME', 'Venusian')`, then `app.name` | Raven's `app_name`: the macOS application menu's title |
| `raven.wait_slice_ms` | `env('RAVEN_WAIT_SLICE_MS', 8)` | longest single wait of a sliced pump, so the longest a loop wake waits where Raven's wait is not Cocoa's ([session](/architecture/session.md)); must be above 0 |
| `raven.backend` | `env('RAVEN_BACKEND', 'vulkan')` | `vulkan` (MoltenVK on macOS) or `null` (lays out, draws nothing) |
| `raven.headless` | `env('RAVEN_HEADLESS', false)` | Raven's null platform: no OS window opens |
| `raven.ui_scale` | `env('RAVEN_UI_SCALE', 0.0)` | a scale on top of the monitor's; 0 keeps Raven's 1.0 |
| `raven.theme` | `env('RAVEN_THEME')` | a built-in theme (`Dark`, `Light`, `GlassDark`, `GlassLight`, `HighContrastDark`, `HighContrastLight`) or a theme XML path; null keeps Raven's `Dark` |

[^config]: config/raven.php
