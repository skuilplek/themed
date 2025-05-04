 # ThemedComponent Technical Overview

 ## Purpose

 The `ThemedComponent` class provides a simple, fluent API to create and render HTML components in PHP using Twig templates. It integrates with a theming system to load component templates from a designated theme directory, supports dynamic parameter definitions, script injection, and debugging/logging.

 ## Architecture

 - Namespace: `Skuilplek\Themed`.
 - Core responsibilities:
   - Bootstrapping a Twig environment for rendering.
   - Loading templates from base theme path and `components/` subdirectory.
   - Parsing Twig docblock comments to extract component parameters.
   - Providing a fluent API (`make()`, dynamic setters, `render()`) for building components.
   - Managing IDs, classes, attributes, inline CSS/JS injection, and visibility control.
   - Logging and debugging support.

 ## Key Components

 - **Static Properties**
   - `$twig`: Singleton `Twig\Environment` used for rendering.
   - `$baseTemplatePath`: Optional override for the theme directory.
   - `$loggerCallback`: Custom logger for debug messages.
   - `$parameterCache`: Caches parsed parameter definitions per template file.

 - **Factory Method**
   - `public static function make(string $component, array $config = []): self`
     - Creates a new `ThemedComponent` instance for the given template name (e.g., `"buttons/button"`).

 - **Fluent API via Magic Methods**
   - Magic `__call()` and `call()`
     - Dynamically maps method calls to either internal setters or content properties.
     - Supports calls like `->content()`, `->addClass()`, or arbitrary content setters like `->title('Hello')`.

 - **Content and Asset Management**
   - `->content(mixed $content)`: Sets main content or an associative array of named slots.
   - `->addClass(string $class)`, `->id(string $id)`, `->addAttribute(string $name, string $value)`.
   - `->addCss(string $css)`, `->addJavaScript(string $script)`
     - Injects inline `<style>`/`<script>` at header/footer via `Themed::headerScripts()` and `Themed::footerScripts()`.

 - **Parameter Parsing**
   - Parses the first Twig docblock (`{# ... #}`) in the component template for lines in the form:
     ```
     - parameterName: type - description
     ```
   - Merges parsed parameters with built-in methods (e.g., `id`, `canSee`, `addClass`).
   - Exposed via `getParameters(): array`.

 ## Rendering Pipeline

 1. **Initialization** (first `make()` call):
    - Determine theme paths from `Themed::getThemePath()` or custom base path.
    - Bootstrap Twig with `FilesystemLoader` and default filters/functions.
    - Register a `component()` Twig function for nested components.

 2. **Build Phase**:
    - Chain setter methods (`->content()`, `->addClass()`, etc.) to configure the component.

 3. **Render Phase** (`->render()`):
    - Early exit if `canSee` is false.
    - Preprocess content (e.g., load SVG for `icons/` components).
    - Inject CSS/JS assets via Themed header/footer scripts.
    - Populate `id`, `classes`, and `attributes` in the template context.
    - Validate template existence and throw exceptions if missing.
    - Render the Twig template with the assembled context.

 ## Debugging and Logging

 - Controlled by environment variables:
   - `THEMED_DEBUG=1` enables Twig debug mode and disables cache.
   - `THEMED_DEBUG_LEVEL={0..3}` controls verbosity of internal logs.
 - Use `ThemedComponent::setLoggerCallback()` to supply a custom logging function.

 ## Error Handling

 - Throws `Exception` if:
   - Component name is empty.
   - Twig environment is not initialized.
   - Template file is not found (includes search paths in the message).

 ## Example Usage

 ```php
 use Skuilplek\Themed\ThemedComponent;

 // Set custom theme path (optional)
 ThemedComponent::setBasePath('/path/to/theme/');

 echo ThemedComponent::make('buttons/button')
     ->addClass('btn-primary')
     ->content('Click Me')
     ->render();
 ```