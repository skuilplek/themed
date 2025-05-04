# Component Development Guidelines

This document provides step-by-step instructions and requirements for adding new UI components to the Themed component library.

## 1. Overview
- All components are implemented as Twig templates under `template/bs5/components` (Bootstrap 5).
- Optional per-component assets (CSS/JS) live alongside the template.
- PHP integration is handled by `ThemedComponent`, which loads parameters and renders the Twig template.

## 2. Directory & Naming Conventions
1. Select or create a **category** folder under `<yourprojectfolder>/template/<yourthemename>/components/`. Category names should be lowercase, hyphen-separated (e.g., `cards`, `buttons`, `form-elements`).
2. For a component named `awesome-widget`, create files:
   - `<yourprojectfolder>/template/<yourthemename>/components/<category>/awesome-widget.twig`
   - (Optional) `<yourprojectfolder>/template/<yourthemename>/components/<category>/awesome-widget.css`
   - (Optional) `<yourprojectfolder>/template/<yourthemename>/components/<category>/awesome-widget.js`
3. File names and folder names must match the component key used in `ThemedComponent::make()`. E.g., `ThemedComponent::make('buttons/awesome-widget')` renders `<yourprojectfolder>/template/<yourthemename>/components/buttons/awesome-widget.twig`.

## 3. Twig Template Structure
1. Start with a Twig comment block documenting **parameters**:
   ```twig
   {# Awesome Widget Component
      - title: string - Widget title text (required)
      - items: array  - List of items to display (optional)
      - class: string - Additional CSS classes (optional)
   #}
   ```
2. Access parameters via the `content` map: `{{ content.title }}`, `content.items`, etc.
3. Use `|default('value')` or null-coalescing (`content.param ?? fallback`) for defaults.
4. Build HTML with Bootstrap 5 markup and utility classes.
5. For nested components, call the Twig function `component('category/name', { /* params */ })`.

## 4. Parameter Documentation
- The first `{# ... #}` block is parsed by `ThemedComponent::extractParameters()` to generate API docs and examples.
- Each line must follow: `- <paramName>: <type> - <description>`.
- Keep lines concise. Describe purpose and any valid values.

## 5. Assets (CSS & JavaScript)
1. If the component requires custom CSS, create `awesome-widget.css` and write plain CSS.
   - Styles will be inlined via `<style>` tags in the page header.
2. If the component needs JavaScript, create `awesome-widget.js`.
   - Scripts will be inlined via `<script>` tags in header or footer depending on directory.
3. Assets are automatically discovered by `Themed::loadScripts()` when rendering the component.

## 6. SVG Icons
- For icon-like components, leverage `Themed::getSvgContent('icon-name')` or use the built-in `icons/icon.twig` component.
- Place raw SVG files under `<yourprojectfolder>/template/<yourthemename>/icons/` or `icons/` inside component folder.

## 7. Unit Tests
1. For each new component, add a PHPUnit test in `tests/Components/<Category>/<ComponentName>Test.php`.
2. Extend `BaseTwigTestCase` to get a preconfigured Twig environment.
3. Write at least one test that renders the `.twig` file with minimal required context and asserts:
   ```php
   $output = $this->twig->render(
       'components/<category>/<component>.twig',
       ['content' => [ /* required params */ ]]
   );
   $this->assertIsString($output);
   $this->assertNotEmpty(trim($output));
   ```
4. Assert that all the content passed to the component is rendered and exists in the output.
5. Run tests with `vendor/bin/phpunit`.

## 8. Example Usage
- Optionally, add an example in the `examples/<category>/<component>.php` script:
  - Use `componentDocumentation()` and `fullExample()` to auto-generate docs and code snippets.
  - Demonstrate component calls via `ThemedComponent::make()` with various parameter combinations.

## 9. PHP Integration
- No PHP class changes are needed unless you expose new features.
- If you introduce new global Twig filters or functions, register them in `src/ThemedComponent.php` when initializing the Twig `Environment`.

## 10. Quality & Review
- Follow existing style patterns (indentation, naming, Bootstrap utilities).
- Keep components lean and focused on a single responsibility.
- Ensure accessibility: include `aria-` attributes, `role`, and semantic HTML where appropriate.
- Get a peer review, test edge cases, and verify in both debug and production modes.

## 11. Creating Custom Themes
To create a custom theme for the Themed library that replaces the default Bootstrap 5 theme, follow these steps:
1. **Replicate the Default Structure**: Create a directory structure that mirrors `template/bs5/`. This includes:
   - `components/`: For Twig partials of UI components.
   - `css/`: For global stylesheets.
   - `js/`: For global scripts to be loaded in the header.
   - `js/footer/`: For scripts to be loaded in the footer.
   - `icons/`: For SVG icon files.
2. **Develop Custom Components**: Within the `components/` directory, organize your custom components by category (e.g., `buttons/`, `navigation/`). Follow the same naming and structure conventions as outlined in earlier sections.
3. **Customize Assets**: Add your custom CSS and JavaScript files in the respective directories to define the styling and behavior of your theme.
4. **Set Environment Variable**: Once your custom theme directory is ready, set the `THEMED_TEMPLATE_PATH` environment variable to point to your theme's root directory. For example:
   ```bash
   export THEMED_TEMPLATE_PATH=/path/to/your/custom/theme
   ```
   This tells the Themed framework to load templates and assets from your custom location instead of the default `template/bs5/`.
5. **Test Your Theme**: Verify that your components render correctly by testing them in your application. Ensure compatibility with the `ThemedComponent::make()` method for calling components.

By creating a custom theme, you can completely redefine the look and feel of the components while still leveraging the rendering and management capabilities of the Themed framework.

By following these guidelines, new components will seamlessly integrate with the Themed library, maintain consistency, and support automated documentation and testing.