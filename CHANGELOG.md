# Changelog

All notable changes to this project will be documented in this file.

## [10.0.0]

### Added
- **Dead Letter Exchange (DLX) & Failed Messages Management**:
    - `DeadLetterConfigurator`: automatically creates `rabbitevents.dlx` topic exchange, declares and binds `{$queue}.failed` queue, and attaches `x-dead-letter-exchange` / `x-dead-letter-routing-key` arguments to declared queues.
    - `rabbitevents:failed`: Artisan command to inspect and list failed messages from the DLX queue with routing key, failed time, and exception details.
    - `rabbitevents:retry`: Artisan command to re-queue failed messages back into the original working queue and acknowledge them from the failed queue (`{id}` or `--all`).
    - `rabbitevents:forget`: Artisan command to remove a specific failed message from the failed queue.
    - `rabbitevents:flush`: Artisan command to delete all failed messages from the failed queue.
- **Message Deduplication**:
    - `Contracts\Deduplicator` interface for deduplication strategies.
    - Built-in deduplicators: `InMemoryDeduplicator`, `NullDeduplicator`, and `RabbitMqDeduplicationPluginStrategy` (for `rabbitmq-message-deduplication` plugin).
    - Automatic `x-deduplication-header` header injection on published messages.
    - Integrated deduplication verification in `Processor::process()` to prevent duplicate event executions.
- **Context Tracing (Laravel Context)**:
    - Automatic injection of `Illuminate\Support\Facades\Context::all()` into message headers (`trace_context`) on publishing.
    - Automatic restoration of trace context in `Processor::withContext()` during listener handling, with guaranteed `Context::flush()` on finish.
- **Transactional Publishing**:
    - `ShouldPublishAfterCommit` interface and `$afterCommit` property/method on `Publishable` trait to delay event publishing until the database transaction successfully commits.
    - Prevents publishing if the transaction is rolled back.
- **Testing Utilities**:
    - `PublishableEventTesting` trait providing `Event::fake()`, `Event::assertPublished()`, `Event::assertPublishedTimes(n)`, `Event::assertNotPublished()`, and `Event::assertNothingPublished()`.
- **Model Observer Generator**:
    - `rabbitevents:make:observer`: Artisan command to scaffold Eloquent model observers publishing RabbitEvents (`User.created`, `User.updated`, etc.).
- **Transport Abstraction**:
    - `Contracts\TransportMessage`, `Contracts\TransportMessageFactory`, and `Contracts\QueueConsumer` interfaces for pluggable transport implementations (such as `rabbitevents/redis-transport`).
    - `AmqpTransportMessage` wrapping Interop AMQP message and unifying application headers and basic properties.
    - `Publishable::publish()` now returns `?string` (the generated message UUID).

### Changed
- **Framework Compatibility**: Added full support for Laravel 11.x, 12.x, and 13.x.
- **Message Architecture**:
    - `Message` class decoupled from direct `Interop\Amqp\AmqpMessage` dependency, now wrapping `Contracts\TransportMessage`.
    - Added unified header accessors: `header()`, `headers()`, and `setHeader()`.
    - Added `id()`, `attempts()`, `increaseAttempts()`, and `hasFailed()` methods.
- **JsonPayload**: Now implements `\JsonSerializable` for seamless serialization with `json_encode()`.
- **Queue Declarations**: `QueueFactory` now automatically configures DLX bindings when durability and DLX are enabled.
- **Worker & Processor**: Integrated deduplication checks and trace context lifecycle into message processing loop.

## [9.0.0]

### Added
- **Payload Interface Pattern**: `Contracts\Payload` interface with `JsonPayload` implementation.
- **Dynamic Serializer Selection**: `SerializerRegistry` selects serializer based on `content_type` header.
- **Listener Attributes**: Support for `#[Listener]` attribute for auto-registration of events.
- **Configurable Queue Durability**: Added `durable` configuration option to control queue persistence (defaults to `true`).
- **Enums**: `WorkerExitStatus` Enum for worker exit codes.
- **Default Serializer Logic**:
    - `SerializerRegistry` now supports a default serializer.
    - `Consumer` uses the default serializer if the content type header is missing.
- **GitHub Actions**: Added CI workflows for `protobuf` extension.
- **Release Automation**: Added `.github/workflows/releases.yml` and updated `bin/release.sh` to automate multi-repo tagging.

### Changed
- **Architecture**:
    - Moved Protobuf support to a separate package: `rabbitevents/protobuf`.
    - Refactored `Listener` discovery and Console commands.
- **PHP Requirement**: Bumped minimum PHP version to 8.2.
- **Readonly Classes**: `Context`, `Publisher`, and `ListenerOptions` are now `readonly`.
- **Message Class**:
    - Refactored to use `Payload` interface instead of `mixed` payload and `Serializer` dependency.
    - Properties `$event` and `$payload` are now `public readonly`.
    - Removed `event()` and `payload()` accessor methods.
- **Serializer Interface**: `deserialize` method now accepts `array $properties` to support context-aware deserialization.
- **Connection Class Moved**: `RabbitEvents\Foundation\Connection` has been moved to `RabbitEvents\Foundation\Amqp\Connection`.
- **Sender**: Decoupled from `Interop\Amqp\AmqpProducer`, now depends on `Interop\Queue\Producer`.
- **Handler**:
    - Removed `Container` dependency, now accepts `failedCallback` in constructor.
    - Updated constructor signature.
    - `$message` is now a public readonly property.
- **Namespace Refactoring**:
    - Console commands moved to `Console` namespace (e.g., `RabbitEvents\Foundation\Console`).
    - `Releaser` moved to `RabbitEvents\Listener\Support`.
- **InstallCommand**: Updated to support Laravel 11+ `bootstrap/providers.php`.
- **Logging**: `General` logger now sanitizes binary payloads (base64 encoding) to prevent log corruption.
- **JsonSerializer**: Stricter `canSerialize` check to avoid claiming non-JSON payloads.

### Removed
- Legacy internal `Support\Payload` class (replaced by `Contracts\Payload`).
