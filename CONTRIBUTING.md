# Contributing to AWS Domain Deleter

Thank you for your interest in contributing to AWS Domain Deleter! This document provides guidelines and information for contributors.

## Table of Contents

- [Code of Conduct](#code-of-conduct)
- [Getting Started](#getting-started)
- [Development Setup](#development-setup)
- [Running Tests](#running-tests)
- [Coding Standards](#coding-standards)
- [Submitting Changes](#submitting-changes)
- [Release Process](#release-process)

## Code of Conduct

This project follows a standard code of conduct. Please be respectful and professional in all interactions.

## Getting Started

1. Fork the repository
2. Clone your fork
3. Create a feature branch
4. Make your changes
5. Submit a pull request

## Development Setup

### Prerequisites

- PHP 8.3 or higher
- Composer
- Git

### Installation

```bash
# Clone the repository
git clone https://github.com/erekle1/aws-domain-deleter.git
cd aws-domain-deleter

# Install dependencies
composer install

# Copy environment configuration
cp env.example .env
# Edit .env with your settings
```

### Project Structure

```
src/
├── Application.php          # Main application orchestrator
├── AWS/
│   ├── CredentialsManager.php   # AWS credentials handling
│   └── ClientFactory.php       # AWS client factory
└── Services/
    ├── DomainManager.php       # Domain validation & loading
    ├── Route53Service.php      # Route 53 hosted zone operations
    ├── Route53DomainsService.php # Route 53 domain registration operations
    └── UserInterface.php       # User interaction & display

tests/
├── Unit/                   # Unit tests
├── Integration/           # Integration tests
└── Fixtures/             # Test data files
```

## Running Tests

### Full Test Suite

```bash
composer test
```

### Code Coverage

```bash
composer test-coverage
```

### Static Analysis

```bash
composer cs-check
```

### Code Style Check

```bash
composer cs-check
```

### Code Style Fix

```bash
composer cs-fix
```

## Coding Standards

### PHP Standards

- Follow PSR-12 coding standard
- Use strict types: `declare(strict_types=1);`
- Write comprehensive docblocks
- Use meaningful variable and method names

### Testing Standards

- Write unit tests for all new functionality
- Maintain minimum 80% code coverage
- Use descriptive test method names
- Mock external dependencies (AWS SDK)

### Security Standards

- Never commit credentials or sensitive data
- Validate all user inputs
- Use environment variables for configuration
- Follow AWS security best practices

## Submitting Changes

### Pull Request Process

1. **Create a feature branch**
   ```bash
   git checkout -b feature/your-feature-name
   ```

2. **Make your changes**
   - Write code following our standards
   - Add tests for new functionality
   - Update documentation as needed

3. **Run the test suite**
   ```bash
   composer test
   composer cs-check
   ```

4. **Commit your changes**
   ```bash
   git add .
   git commit -m "feat: add new feature description"
   ```

5. **Push to your fork**
   ```bash
   git push origin feature/your-feature-name
   ```

6. **Create a pull request**
   - Use a descriptive title
   - Include detailed description
   - Reference any related issues
   - Ensure all CI checks pass

### Commit Message Format

Use conventional commits format:

```
type(scope): description

[optional body]

[optional footer]
```

Types:
- `feat`: New features
- `fix`: Bug fixes
- `docs`: Documentation changes
- `style`: Code formatting
- `refactor`: Code refactoring
- `test`: Test additions/modifications
- `chore`: Maintenance tasks

### Pull Request Requirements

- [ ] Code follows PSR-12 standards
- [ ] All tests pass
- [ ] Code coverage maintained
- [ ] Documentation updated
- [ ] No merge conflicts
- [ ] Descriptive commit messages

## Release Process

### Version Numbering

We follow [Semantic Versioning](https://semver.org/):

- `MAJOR.MINOR.PATCH`
- MAJOR: Breaking changes
- MINOR: New features (backward compatible)
- PATCH: Bug fixes (backward compatible)

### Release Checklist

1. Update version in `composer.json`
2. Update `CHANGELOG.md`
3. Ensure all tests pass
4. Create release notes
5. Tag the release
6. Update documentation

## Development Guidelines

### Adding New Features

1. **Design First**
   - Consider backward compatibility
   - Think about error handling
   - Plan the user interface

2. **Test-Driven Development**
   - Write tests first when possible
   - Cover happy path and edge cases
   - Mock external dependencies

3. **Documentation**
   - Update README if needed
   - Add inline code comments
   - Update API documentation

### Working with AWS

- Always use dry-run mode for testing
- Never use production AWS accounts for development
- Use IAM users with minimal required permissions
- Be aware of AWS costs when testing

### Security Considerations

- Sanitize all user inputs
- Use prepared statements for any database operations
- Validate domain names before processing
- Log security-relevant events

## Testing Guidelines

### Unit Tests

- Test individual classes in isolation
- Mock all external dependencies
- Use descriptive test names
- Follow AAA pattern (Arrange, Act, Assert)

### Integration Tests

- Test complete workflows
- Use test fixtures for data
- Clean up after tests
- Test error conditions

### Best Practices

```php
public function testMethodDoesExpectedBehaviorWithValidInput(): void
{
    // Arrange
    $input = 'test-input';
    $expectedOutput = 'expected-result';
    
    // Act
    $result = $this->service->method($input);
    
    // Assert
    $this->assertEquals($expectedOutput, $result);
}
```

## Getting Help

- Check existing issues and discussions
- Read the documentation thoroughly
- Ask questions in GitHub Discussions
- Join our community channels

## License

By contributing to this project, you agree that your contributions will be licensed under the MIT License.

Thank you for contributing! 🎉
