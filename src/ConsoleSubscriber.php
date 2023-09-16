<?php
declare(strict_types=1);

namespace Janmikes\SymfonyConsoleSentryCronMonitoring;

use ReflectionClass;
use Sentry\CheckInStatus;
use Sentry\MonitorConfig;
use Sentry\MonitorSchedule;
use Sentry\SentrySdk;
use Sentry\State\Scope;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\Console\Event\ConsoleErrorEvent;
use Symfony\Component\Console\Event\ConsoleSignalEvent;
use Symfony\Component\Console\Event\ConsoleTerminateEvent;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

final class ConsoleSubscriber implements EventSubscriberInterface
{
    private null|string $monitorSlug = null;
    private null|string $checkInId = null;

    public function __construct(
        private readonly string $environment,
    ) {
    }

    /**
     * @return array<string, string>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            ConsoleEvents::COMMAND => 'onCommand',
            ConsoleEvents::SIGNAL => 'onSignal',
            ConsoleEvents::TERMINATE => 'onTerminate',
            ConsoleEvents::ERROR => 'onError',
        ];
    }

    public function onCommand(ConsoleCommandEvent $event): void
    {
        $command = $event->getCommand();

        if ($command === null) {
            return;
        }

        $classReflection = new ReflectionClass($command::class);
        $monitorAttributes = $classReflection->getAttributes(Monitor::class);

        foreach ($monitorAttributes as $monitorAttribute) {
            /** @var Monitor $monitor */
            $monitor = $monitorAttribute->newInstance();

            // Monitor configuration (from attribute) is not for this env, do not monitor ;-)
            if ($monitor->environment->value !== $this->environment) {
                continue;
            }

            $commandArguments = $event->getInput()->getArguments();
            unset($commandArguments['command']);
            $commandArguments = array_filter(array_values($commandArguments));

            // When arguments differs, this monitor should not be used
            if ($monitor->arguments !== $commandArguments) {
                continue;
            }

            $this->monitorSlug = $this->generateSlugName($command->getName(), $event->getInput());

            $hub = SentrySdk::getCurrentHub();
            $hub->configureScope(function (Scope $scope): void {
                $scope->setContext('monitor', [
                    'slug' => $this->monitorSlug ?? '',
                ]);
            });

            $monitoringConfig = new MonitorConfig(
                MonitorSchedule::crontab($monitor->cronExpression),
                $monitor->checkinMargin,
                $monitor->maxRuntime,
                $monitor->timezone,
            );

            $this->checkInId = $hub->captureCheckIn(
                $this->monitorSlug,
                CheckInStatus::inProgress(),
                null,
                $monitoringConfig,
            );
        }
    }

    public function onSignal(ConsoleSignalEvent $event): void
    {
        $this->finishMonitoring(CheckInStatus::ok());
    }

    public function onError(ConsoleErrorEvent $event): void
    {
        $this->finishMonitoring(CheckInStatus::error());
    }

    public function onTerminate(ConsoleTerminateEvent $event): void
    {
        $this->finishMonitoring(CheckInStatus::ok());
    }

    private function finishMonitoring(CheckInStatus $status): void
    {
        if ($this->checkInId === null) {
            return;
        }

        SentrySdk::getCurrentHub()->captureCheckIn(
            slug: $this->monitorSlug ?? '',
            status: $status,
            checkInId: $this->checkInId,
        );
    }

    private function generateSlugName(null|string $command, InputInterface $input): string
    {
        /** @var array<string|null> $arguments */
        $arguments = $input->getArguments();

        if ($command === null) {
            $command = $arguments['command'];
        }

        unset($arguments['command']);

        $slug = str_replace(':', '-', $command ?? '');

        foreach ($arguments as $argument) {
            if ($argument !== null) {
                $slug .= '-' . $argument;
            }
        }

        return $slug;
    }
}