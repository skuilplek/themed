 # Technical Overview

## Project Summary

- **Name:** skuilplek/themed
- **Description:** A component-based theme engine for PHP applications, providing modular, maintainable, and extensible HTML components.
- **Type:** PHP library (Composer package)
- **License:** LGPL-3.0-or-later
- **Author:** Hannes Coetzee

## Directory Structure

```text
.
├── composer.json       # Project metadata & dependencies
├── src/                # Core PHP classes
│   ├── Themed.php
│   └── ThemedComponent.php
├── template/           # Default theme assets (Bootstrap 5)
│   └── bs5/
│       ├── components/  # Twig partials (buttons, feedback, layout, etc.)
│       ├── css/         # Global stylesheet
│       ├── js/          # Global scripts
│       ├── js/footer/   # Footer-specific scripts
│       └── icons/       # SVG icon files
├── README.md           # Project overview and installation guide
├── LICENSE.md          # License file
├── GUIDELINES.md       # Coding standards and best practices
├── OVERVIEW.md         # Additional project overview details
├── TECHNICAL.md        # Technical documentation of the framework
├── .gitignore          # Ignore patterns
```

## Core Functionality

### 1. Themed (src/Themed.php)

- **Session-based script management:** Stores header/footer scripts in `$_SESSION['sk_themed']`.
- **Asset loading:** `loadScripts($component = "")` inlines CSS/JS from theme path (`css/`, `js/`, `js/footer/`, `components/`).
- **Font inlining:** `processStylesWithFonts()` embeds font files as Base64 data URIs.
- **Minification:** `minifyCss()` and `minifyJs()` remove comments/whitespace when debug is disabled.
- **SVG handling:** `getSvgContent($name)` loads, optimizes, and caches SVG icons.
- **Logging:** `log($message)` writes to a rotating log file (1 MB max, up to 5 backups).

### 2. ThemedComponent (src/ThemedComponent.php)
- **Static Properties:**
  - `$twig`: Singleton `Twig\Environment` used for rendering.
  - `$templatePath`: Path to theme templates, defaults to `template/bs5/` but overridable via `THEMED_TEMPLATE_PATH`.
- **Component Creation:** `make($name)` creates a component instance by name (e.g., `navigation/navbar`).
- **Attribute Management:** Methods like `id()`, `addClass()`, `addAttribute()` for HTML attributes.
- **Rendering:** `render()` processes Twig templates with component data, returning HTML.
- **Visibility Control:** `canSee($condition)` toggles component visibility based on logic (e.g., user permissions).
- **Debugging:** Supports `THEMED_DEBUG`, `THEMED_DEBUG_LEVEL`, and `THEMED_DEBUG_LOG` environment variables.

## Custom Themes

You can create a custom theme by replicating the structure of the default `template/bs5/` directory. This structure includes subdirectories for components, CSS, JS, and icons. Once you've created your custom theme, set the `THEMED_TEMPLATE_PATH` environment variable to point to your theme's directory. For example:

```bash
export THEMED_TEMPLATE_PATH=/path/to/your/custom/theme
```

This allows the framework to load your custom templates instead of the default Bootstrap 5 theme, enabling full customization of the appearance and behavior of components.

## Environment Variables

- **`THEMED_TEMPLATE_PATH`**: Overrides the default template directory (`template/bs5/`).
- **`THEMED_DEBUG`**: Enables debug mode (1 for on, 0 for off).
- **`THEMED_DEBUG_LEVEL`**: Sets debug verbosity (0-3).
- **`THEMED_DEBUG_LOG`**: Specifies log file path for debug output.

## Theming & Templates

- **Default theme path:** `template/bs5/` (Bootstrap 5).
- **Template conventions:**
  - Templates: `components/<category>/<component>.twig`
  - Inline documentation: `{# - paramName: type - description #}`.
- **Asset structure:** `css/`, `js/`, `js/footer/`, `icons/`.

## Configuration & Environment

- **Composer:** PSR-4 autoload (`Skuilplek\\Themed\\` → `src/`), requires `php >=8.2` and `twig/twig ^3.0`.
- **Environment variables:**
  - `THEMED_TEMPLATE_PATH` — custom theme directory.
  - `THEMED_DEBUG` — enable debug mode (enables Twig debug mode and disables template cache; 0/1).
  - `THEMED_DEBUG_LEVEL` — verbosity (0–3).
  - `THEMED_DEBUG_LOG` — debug log file path.

## Example Application (examples/)

- Demonstrates library usage via a PHP site.
- **Key files:**
  - `index.php` — routing, navigation generation, page rendering.
  - `functions.php` — helpers for validation, documentation, and examples.
  - `examples/{category}/{component}.php` — per-component demos with code snippets.
- **Dependencies:** `erusev/parsedown` for Markdown, Twig, and path-based Composer repository to the main library.

## Dependencies

- **Production:**
  - PHP ≥ 8.2
  - Twig/Twig ≥ 3.0
- **Examples:**
  - Parsedown ^1.7

## Testing & CI

- No automated tests or CI workflows defined.
- Manual testing via the `examples/` application.

## License

Licensed under the [LGPL-3.0-or-later](LICENSE.md).