<?php
declare(strict_types=1);

namespace Janmikes\SymfonyConsoleSentryCronMonitoring;

enum Environment: string
{
    case Production = 'production';
    case Staging = 'staging';
    case Review = 'review';
    case Development = 'dev';
}
