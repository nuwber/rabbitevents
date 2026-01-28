# Changelog

All notable changes to this project will be documented in this file.

## [9.0.0] - 2026-01-26

### Added
- **Payload Interface Pattern**: `Contracts\Payload` interface with `JsonPayload` and `ProtobufPayload` implementations.
- **Dynamic Serializer Selection**: `SerializerRegistry` selects serializer based on `content_type` header.
- **Protobuf Support**: Out-of-the-box support for Google Protobuf messages.
    - Automatic `type` header setting in Publisher.
    - Automatic class re-hydration in Consumer.
- **Listener Attributes**: Support for `#[Listener]` attribute for auto-registration of events.
- **Configurable Queue Durability**: Added `durable` configuration option to control queue persistence (defaults to `true`).
- **Enums**: `WorkerExitStatus` Enum for worker exit codes.

### Changed
- **PHP Requirement**: Bumped minimum PHP version to 8.2.
- **Readonly Classes**: `Context`, `Publisher`, and `ListenerOptions` are now `readonly`.
- **Message Class**: Refactored to use `Payload` interface instead of `mixed` payload and `Serializer` dependency.
- **Serializer Interface**: `deserialize` method now accepts `array $properties` to support context-aware deserialization.

### Removed
- Legacy internal `Support\Payload` class (replaced by `Contracts\Payload`).

