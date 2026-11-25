---
okf_version: "0.2"
---

# jovian/venusian-raven

* [Session](architecture/session.md) - Raven started once per process, its quit handed to the app, and a pump that is one frame wait, ended by the loop's descriptor through Cocoa on macOS or in slices elsewhere.
* [Window](architecture/window.md) - A Raven window with a vbox content widget, wired to Topside's window lifecycle; activation and scale compared after each pump, owned child windows block their parent's input.
* [Menu bar](architecture/menu-bar.md) - One Raven MenuBar per window, first in its box; Raven's macOS application menu kept, its Quit answered by the shown bar; shortcut text where AppKit would take the key first.
* [UI primitives](architecture/ui-primitives.md) - The Raven primitives on Topside's TK classes, the native widget each one is, and which of Topside's settings Raven shows natively.

# API

* [Config](api/config.md) - config/raven.php keys read by the driver and the toolkit.
