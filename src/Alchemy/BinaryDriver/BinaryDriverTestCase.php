<?php

namespace Alchemy\BinaryDriver;

use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Process\Process;

/**
 * Convenient PHPUnit methods for testing BinaryDriverInterface implementations.
 */
class BinaryDriverTestCase extends TestCase
{
    /**
     * @return ProcessBuilderFactoryInterface
     */
    public function createProcessBuilderFactoryMock()
    {
        return $this->createMock('Alchemy\BinaryDriver\ProcessBuilderFactoryInterface');
    }

    /**
     * @param integer $runs        The number of runs expected
     * @param Boolean $success     True if the process expects to be successfull
     * @param string  $commandLine The commandline executed
     * @param string  $output      The process output
     * @param string  $error       The process error output
     *
     */
    public function createProcessMock(
        $runs = 1,
        $success = true,
        $commandLine = null,
        $output = '',
        $error = '',
        $callback = false
    ) {
        $process = $this->getMockBuilder('Symfony\Component\Process\Process')
            ->disableOriginalConstructor()
            ->getMock();

        $builder = $process->expects($this->exactly($runs))
            ->method('run');

        if (true === $callback) {
            $builder->with($this->isInstanceOf('Closure'));
        }

        $process
            ->method('isSuccessful')
            ->willReturn($success);

        foreach ([
                     'getOutput'      => $output,
                     'getErrorOutput' => $error,
                     'getCommandLine' => $commandLine,
                 ] as $command => $value) {
            $process
                ->method($command)
                ->willReturn($value);
        }

        return $process;
    }

    /**
     */
    public function createLoggerMock()
    {
        return $this->createMock('Psr\Log\LoggerInterface');
    }

    /**
     */
    public function createConfigurationMock()
    {
        return $this->createMock('Alchemy\BinaryDriver\ConfigurationInterface');
    }
}
