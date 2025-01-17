<?php

namespace Alchemy\Tests\BinaryDriver;

use LogicException;
use Symfony\Component\Process\Process;

class LTSProcessBuilder extends Process
{
    private $arguments;
    private $prefix;
    private $timeout;

    public function __construct(array $arguments = array())
    {
        $this->arguments = $arguments;
        parent::__construct($arguments);
    }

    public function setArguments(array $arguments)
    {
        $this->arguments = $arguments;

        return $this;
    }

    public function setPrefix($prefix)
    {
        $this->prefix = $prefix;

        return $this;
    }

    public function setTimeout($timeout): static
    {
        $this->timeout = $timeout;

        return $this;
    }

    public function getProcess()
    {
        if (!$this->prefix && !count($this->arguments)) {
            throw new LogicException('You must add() command arguments before calling getProcess().');
        }

        $args = $this->prefix ? array_merge(array($this->prefix), $this->arguments) : $this->arguments;
        $script = array_map('escapeshellarg', $args);

        return new Process($script, null, null, null, $this->timeout);
    }
}
