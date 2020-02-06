<?php

declare(strict_types=1);

namespace Lamoda\TacticianLockingMiddleware;

use League\Tactician\Middleware;

/**
 * If another command is already being executed, locks the command bus and
 * queues the new incoming commands until the first has completed.
 */
class LockingMiddleware implements Middleware
{
    /**
     * @var bool
     */
    private $isExecuting;

    /**
     * @var callable[]
     */
    private $queue = [];

    /**
     * This middleware differs from the original in a way it handles Throwable, not only Exception.
     *
     * @param object   $command
     * @param callable $next
     *
     * @throws \Throwable
     *
     * @return mixed|void
     */
    public function execute($command, callable $next)
    {
        $this->queue[] = static function () use ($command, $next) {
            return $next($command);
        };

        if ($this->isExecuting) {
            return;
        }
        $this->isExecuting = true;

        try {
            $returnValue = $this->executeQueuedJobs();
        } catch (\Throwable $e) {
            $this->isExecuting = false;
            $this->queue = [];
            throw $e;
        }

        $this->isExecuting = false;

        return $returnValue;
    }

    /**
     * Process any pending commands in the queue. If multiple, jobs are in the
     * queue, only the first return value is given back.
     *
     * @return mixed
     */
    protected function executeQueuedJobs()
    {
        $returnValues = [];
        while ($resumeCommand = array_shift($this->queue)) {
            $returnValues[] = $resumeCommand();
        }

        return array_shift($returnValues);
    }
}
