# Contributing to Terra

Thank you for your interest in contributing to Terra! This document provides guidelines for contributing to the project.

## Getting Started

1. Fork the repository
2. Clone your fork: `git clone https://github.com/YOUR-USERNAME/terra.git`
3. Create a feature branch: `git checkout -b feature/your-feature-name`
4. Install dependencies: `composer install`

## Development Setup

### Prerequisites

- PHP >= 7.4
- Composer
- ZMQ Extension (`ext-zmq`)
- Janus Gateway (for testing)

### Installing ZMQ Extension

**Ubuntu/Debian:**
```bash
sudo apt-get install libzmq3-dev php-zmq
```

**macOS:**
```bash
brew install zmq
pecl install zmq-beta
```

## Code Standards

- Follow PSR-12 coding standards
- Add PHPDoc comments to all classes and methods
- Keep methods focused and single-purpose
- Use type hints for parameters and return types

## Testing

Before submitting a pull request:

1. Test your changes with a real Janus Gateway instance
2. Ensure all examples still work
3. Add examples for new features
4. Update documentation as needed

## Pull Request Process

1. Update the README.md with details of changes if applicable
2. Update the CHANGELOG.md with your changes
3. Ensure your code follows the project's coding standards
4. Write clear, descriptive commit messages
5. Create a pull request with a clear title and description

## Commit Messages

- Use the present tense ("Add feature" not "Added feature")
- Use the imperative mood ("Move cursor to..." not "Moves cursor to...")
- Limit the first line to 72 characters or less
- Reference issues and pull requests liberally after the first line

## Adding New Features

When adding new features:

1. Check if there's an existing issue for the feature
2. If not, create an issue to discuss the feature first
3. Implement the feature in a focused way
4. Add examples demonstrating the feature
5. Update documentation

## Adding Plugin Controllers

To add a new plugin controller:

1. Create a new class in `src/Plugin/`
2. Extend from appropriate base if needed
3. Implement the plugin-specific admin methods
4. Add an example in `examples/`
5. Document the controller in README.md

Example structure:
```php
<?php

namespace Terra\Plugin;

use React\Promise\Promise;
use Terra\Admin\AdminClient;

class YourPluginAdmin
{
    private $client;
    private $pluginId = 'janus.plugin.yourplugin';

    public function __construct(AdminClient $client)
    {
        $this->client = $client;
    }

    // Add your methods here
}
```

## Reporting Bugs

1. Check if the bug has already been reported
2. If not, create a new issue with:
   - Clear title and description
   - Steps to reproduce
   - Expected behavior
   - Actual behavior
   - Environment details (PHP version, OS, etc.)
   - Code samples if applicable

## Feature Requests

1. Check if the feature has already been requested
2. Create a new issue with:
   - Clear title and description
   - Use cases for the feature
   - Examples of how it would work
   - Any relevant references

## Code of Conduct

- Be respectful and inclusive
- Welcome newcomers
- Focus on constructive feedback
- Help others learn and grow

## Questions?

Feel free to open an issue with the "question" label if you have any questions about contributing.

## License

By contributing to Terra, you agree that your contributions will be licensed under the MIT License.
