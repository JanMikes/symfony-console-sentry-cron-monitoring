<?php
declare(strict_types=1);

namespace Janmikes\SymfonyConsoleSentryCronMonitoring;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
final class Monitor
{
    /**
     * @param array<string> $arguments
     */
    public function __construct(
        readonly public string $cronExpression,
        readonly public Environment $environment,
        readonly public null|int $checkinMargin = null,
        readonly public null|int $maxRuntime = null,
        readonly public null|string $timezone = 'Europe/Prague',
        readonly public array $arguments = [],
    ) {
    }
}
