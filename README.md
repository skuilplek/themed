# Themed - PHP Component Framework

**Themed** is a PHP-based component framework designed to streamline the development of themed web applications. It provides a structured approach to building reusable components with support for Bootstrap 5 and other styling frameworks.

## Purpose

Themed aims to simplify the creation of modular, reusable UI components for web applications. It offers a foundation for building responsive layouts and navigation elements, with support for templating using Twig and easy integration with popular CSS frameworks.

## Project Structure

- **src/**: Core source code for the Themed framework, including the main `ThemedComponent` class.
- **template/**: Twig templates for rendering components, organized by framework (e.g., Bootstrap 5 under `bs5/`).
- **tests/**: Unit tests to ensure the reliability of the framework components.

## Getting Started

### Prerequisites
- PHP and Composer for dependency management.

### Installation
1. **Install as a Composer Package**: Themed is available as a Composer package. Add it to your project by running:
   ```bash
   composer require skuilplek/themed
   ```
2. **View Examples Online**: You can explore component usage examples and documentation online at [http://skuilplek.org/themed/](http://skuilplek.org/themed/).

## Usage

Create your own components by extending `ThemedComponent` found in `src/`. Use the provided Twig templates in `template/` as a base for rendering your components with consistent styling.

## Guidelines

Refer to `GUIDELINES.md` for coding standards and best practices when contributing to or extending the framework. Detailed instructions are provided for creating new components and custom themes.

## Additional Documentation

- `GUIDELINES.md`: Provides a technical overview of the Themed framework, including core functionality, directory structure, and environment variables.
- `OVERVIEW.md`: Offers additional details about the project's purpose and structure, complementing the information in this README.

## License

This project is licensed under the terms detailed in `LICENSE.md`.