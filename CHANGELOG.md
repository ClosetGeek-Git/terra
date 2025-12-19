# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [0.1.0] - 2024-12-19

### Added
- Initial release of Terra Janus Admin Framework
- Core ZeroMQ transport layer with ReactPHP integration
- AdminClient class with full Admin API support
- Configuration management system with dot notation
- Logging system using Monolog
- Custom exceptions for better error handling
- Plugin-specific admin controllers:
  - VideoRoomAdmin - Complete room and participant management
  - VideoCallAdmin - Call session management
  - StreamingAdmin - Mountpoint administration
  - EchoTestAdmin - Plugin diagnostics
  - RecordPlayAdmin - Recording management
- Event handling system for Janus events
- Comprehensive examples:
  - Basic usage example
  - VideoRoom plugin example
  - Streaming plugin example
  - Event handler example
  - Interactive CLI tool
- Complete documentation in README.md
- Configuration example file
- MIT License

### Core Features
- Asynchronous request/response handling with Promises
- Transaction ID management
- Request timeout handling
- Connection error recovery
- JSON message validation
- Plugin registration system
- Environment variable support

### Admin API Methods
- Server information retrieval
- Session management
- Handle management
- Log level control
- Packet capture control
- Message statistics

[0.1.0]: https://github.com/ClosetGeek-Git/terra/releases/tag/v0.1.0
