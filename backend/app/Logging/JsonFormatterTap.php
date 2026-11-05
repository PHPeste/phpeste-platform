<?php

declare(strict_types=1);

namespace App\Logging;

use Illuminate\Log\Logger;
use Monolog\Formatter\JsonFormatter;
use Monolog\Handler\FormattableHandlerInterface;
use Monolog\Handler\ProcessableHandlerInterface;
use Monolog\Logger as MonologLogger;

class JsonFormatterTap
{
    public function __invoke(Logger $logger): void
    {
        $monolog = $logger->getLogger();

        if (!$monolog instanceof MonologLogger) {
            return;
        }

        foreach ($monolog->getHandlers() as $handler) {
            if ($handler instanceof FormattableHandlerInterface) {
                $handler->setFormatter(new JsonFormatter);
            }

            if ($handler instanceof ProcessableHandlerInterface) {
                $handler->pushProcessor(new RedactSensitiveDataProcessor);
            }
        }
    }
}
