# Installation

1. Install package - `composer require janmikes/symfony-console-sentry-cron-monitoring`
2. Register `Janmikes\SymfonyConsoleSentryCronMonitoring\MonitoringConsoleSubscriber` service to your application (must be tagged as event subscriber)
3. Add `Monitor` attributes to your console commands
4. Profit!


### Minimal
```php
#[Monitor('0 */4 * * *', Environment::Production)]
class MySuperCoolCommand
```

### Full
```php
#[Monitor(
    cronExpression: '0 */4 * * *',
    environment: Environment::Production,
    checkinMargin: 10,
    maxRuntime: 60,
    timezone: 'Europe/Prague',
    arguments: ['some-arg', 'some-another-arg'],
)]
class MySuperCoolCommand
```