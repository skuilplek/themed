# Themed Component Library Overview

This project provides a modular, maintainable, and extensible set of HTML components, organized for easy usage in modern PHP/Twig projects. All components are structured for maximum reusability and consistency, leveraging layout and content primitives. The default theme is Bootstrap 5, but the project is designed to be easily extended to other popular front-end frameworks and use entire custom themes as well.

## Installation

You can install the Themed Component Library as a Composer package by running the following command in your project directory:

```bash
composer require skuilplek/themed
```

After installation, you can autoload the library in your PHP project by including the Composer autoloader:

```php
require 'vendor/autoload.php';
```

## Template Structure

You can create a custom theme by using this structure and setting the `THEMED_TEMPLATE_PATH` environment variable to point to your custom theme. The default included theme is in `template/bs5/`.

```
template/bs5/
├── components/
│   ├── buttons/      # Button components (basic, groups, toggles)
│   ├── content/      # Content components (accordion, alerts, badges, cards, images, lists, tables)
│   ├── extra/        # Extra utility components
│   ├── feedback/     # Feedback components (alerts, badges, progress)
│   ├── form/         # Form components (inputs, checkboxes, radios, selects)
│   ├── grid/         # Grid system components
│   ├── header/       # Header and title components
│   ├── html/         # Basic HTML element components
│   ├── icons/        # Icon components for SVG icons
│   ├── layout/       # Layout components (containers, sections, pages)
│   ├── media/        # Media components (carousel, figures)
│   ├── navigation/   # Navigation components (navbar, breadcrumbs, pagination, tabs)
│   └── overlays/     # Overlay components (modals, dropdowns, offcanvas, popovers, toasts)
├── css/
│   ├── theme.css     #Global theme styles
├── js/
│   ├── theme.js      #Global theme scripts
├── js/footer/
│   ├── theme.js      #Global theme scripts which will be added to the footer instead of the header
```

## CSS and Javascript

The CSS and Javascript files are loaded automatically when the Themed::loadScripts() method is called. You can also load specific scripts using the `Themed::headerScripts('script')` and `Themed::footerScripts('script')` methods. All loaded scripts can be accessed using the `Themed::headerScripts()` and `Themed::footerScripts() ` methods without passing any options. 

## Available Components

More components will be added later.

### Buttons
- Button

### Feedback
- Alert
- Toast

### Icons
- Icon

### Layout
- Page
- Section

### Navigation
- Navbar

## Configuration

The Themed Component Library can be configured using the following environment variables:

- **THEMED_TEMPLATE_PATH**: Specifies the path to the template directory. Default is `/var/www/html/template/bs5/`.
  ```bash
  export THEMED_TEMPLATE_PATH=/var/www/html/template/bs5/
  ```
- **THEMED_DEBUG**: Enables or disables debug mode. Set to `1` to enable debugging. Default is `0`.
  ```bash
  export THEMED_DEBUG=1
  ```
- **THEMED_DEBUG_LEVEL**: Sets the debug level for logging. Levels range from `0` (minimal logging) to `3` (detailed logging). Default is `0`.
  ```bash
  export THEMED_DEBUG_LEVEL=0
  ```
- **THEMED_DEBUG_LOG**: Specifies the file path for debug logs. Default is `/var/www/html/themed.log`.
  ```bash
  export THEMED_DEBUG_LOG=/var/www/html/themed.log
  ```

These environment variables can be set in your shell configuration file or directly in your deployment scripts to customize the behavior of the library.

## Guidelines

- **Modularity:** All components are structured to be reusable and composable. Use `layout/section` and `layout/page` to wrap content for consistent structure.
- **Maintainability:** Components follow a clear directory and naming convention. Avoid hardcoded HTML in templates; always use the provided components.
- **Extensibility:** Add new components by following the existing structure. Group related components in their respective category folders.
- **Styling:** Bootstrap 5 is the default style framework. Custom styles should be minimal and added only when necessary.
- **Templating:** Twig is the recommended templating engine. All components are available as Twig partials.

## Usage

To use the Themed Component Library in your PHP project, you can initialize and render components as shown below:

```php
use Themed\ThemedComponent;

// Example of creating and rendering an icon component
ThemedComponent::make("icons/icon")
	->addAttribute('data-foo="bar" data-bar="baz"') //Add an attribute to the element. This is a string like 'data-foo="bar"' or multiple attributes in a single string like 'data-foo="bar" data-bar="baz"' (optional)
	->addClass('custom-class1 custom-class2 custom-class3') //Add a single "classname" to the element or multiple classes as a string like "class1 class2 class3" (optional)
	->addCss('<style> .custom-class {...} </style>') //string - Add css styles to the element. This should be '<style> custom-class {...} </style>' (optional)
	->addJavaScript('<script>console.log("Hello World!")</script>') //string - Add a script to the element. This is a string like '<script>console.log("Hello World!")</script>' (optional)
	->canSee($usergroup == 'admin') //bool - Whether the component should be visible or not (optional)
	->color('#ededed') //string - Custom color value (hex, rgb, or valid CSS color) (optional)
	->id('my-icon-id') //string - The id of the element. If no id is supplied, a random one will be generated (optional)
	->name('star') //string - Name of the SVG icon file without extension
	->preset_color('primary') //string - Bootstrap color (primary, secondary, success, danger, warning, info, light, dark) (optional)
	->preset_size('lg') //string - Predefined size (sm, md, lg, xl, 2xl, 3xl) (optional)
	->size('26px') //string - Custom size with unit (e.g., '12px', '1.5rem', '2em') (optional)
	->title('a custom button hover title') //string - Title attribute for accessibility (recommended for a11y) (optional)
	->render();
```

For more details, see the individual component files in `template/bs5/components/`.

## License

This project is licensed under the LGPL v3.+ License.