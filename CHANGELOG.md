# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.1] - 2025-10-14

### Fixed
- Added Symfony Flex recipe to automatically create configuration file during installation
- Fixed installation error: "The child config 'domain' under 'habityzer_kinde' must be configured"
- Environment variables now properly initialized with example values during `composer require`

### Changed
- Updated installation documentation with troubleshooting steps
- Added automatic creation of `config/packages/habityzer_kinde.yaml` via Flex recipe

## [1.0.0] - 2025-10-13

### Added
- Initial release of Habityzer Kinde Bundle
- JWT token validation using JWKS
- Symfony Security integration with custom authenticator
- User synchronization through `KindeUserProviderInterface`
- Webhook support for Kinde events
- Event system for business logic integration
- Support for user events (authenticated, updated, deleted)
- Support for subscription events (created, updated, cancelled, reactivated)
- Token validation service with JWKS caching
- Debug command for token inspection
- Comprehensive documentation and examples

### Features
- PHP 8.2+ support
- Symfony 6.4 and 7.x compatibility
- PSR-4 autoloading
- MIT License

