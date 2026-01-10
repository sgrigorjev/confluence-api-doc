<?php

declare(strict_types=1);

namespace ConfluenceApiDoc\Logger;

use Psr\Log\AbstractLogger;
use Psr\Log\LogLevel;
use Symfony\Component\Console\Output\OutputInterface;

class ConsoleLogger extends AbstractLogger
{
    private OutputInterface $output;

    public function __construct(OutputInterface $output)
    {
        $this->output = $output;
    }

    public function log($level, $message, array $context = []): void
    {
        $verbosity = $this->output->getVerbosity();

        switch ($level) {
            case LogLevel::EMERGENCY:
            case LogLevel::ALERT:
            case LogLevel::CRITICAL:
            case LogLevel::ERROR:
                $show = $verbosity >= OutputInterface::VERBOSITY_VERBOSE;
                $color = 'bright-red';
                break;
            case LogLevel::WARNING:
                $show = $verbosity >= OutputInterface::VERBOSITY_VERBOSE;
                $color = 'bright-yellow';
                break;
            case LogLevel::NOTICE:
                $show = $verbosity >= OutputInterface::VERBOSITY_VERY_VERBOSE;
                $color = 'yellow';
                break;
            case LogLevel::INFO:
                $show = $verbosity >= OutputInterface::VERBOSITY_VERY_VERBOSE;
                $color = 'bright-blue';
                break;
            case LogLevel::DEBUG:
            default:
                $show = $verbosity === OutputInterface::VERBOSITY_DEBUG;
                $color = 'gray';
        }

        if ($context) {
            $message .= ' ' . json_encode($context);
        }

        if ($show) {
            $this->output->writeln(sprintf('<fg=%s>%s: %s</>', $color, $level, $message));
        }
    }
}
