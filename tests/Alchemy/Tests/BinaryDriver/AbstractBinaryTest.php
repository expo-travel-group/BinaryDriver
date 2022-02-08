<?php /** @noinspection PhpExpressionResultUnusedInspection */

namespace Alchemy\Tests\BinaryDriver;

use Alchemy\BinaryDriver\AbstractBinary;
use Alchemy\BinaryDriver\BinaryDriverTestCase;
use Alchemy\BinaryDriver\Configuration;
use Alchemy\BinaryDriver\Exception\ExecutableNotFoundException;
use Alchemy\BinaryDriver\Listeners\ListenerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\Process\ExecutableFinder;
use Alchemy\BinaryDriver\Listeners\Listeners;
use Symfony\Component\Process\Process;
use Alchemy\BinaryDriver\ProcessRunnerInterface;
use Alchemy\BinaryDriver\ProcessBuilderFactoryInterface;
use Alchemy\BinaryDriver\ConfigurationInterface;
use Psr\Log\LoggerInterface;

class AbstractBinaryTest extends BinaryDriverTestCase
{
    protected function getPhpBinary(): ?string
    {
        $finder = new ExecutableFinder();
        $php = $finder->find('php');

        if (null === $php) {
            $this->markTestSkipped('Unable to find a php binary');
        }

        return $php;
    }

    public function testSimpleLoadWithBinaryPath(): void
    {
        $php = $this->getPhpBinary();
        $imp = Implementation::load($php);
        $this->assertInstanceOf(Implementation::class, $imp);

        $this->assertEquals($php, $imp->getProcessBuilderFactory()->getBinary());
    }

    public function testMultipleLoadWithBinaryPath(): void
    {
        $php = $this->getPhpBinary();
        $imp = Implementation::load(array('/zz/path/to/unexisting/command', $php));
        $this->assertInstanceOf(Implementation::class, $imp);

        $this->assertEquals($php, $imp->getProcessBuilderFactory()->getBinary());
    }

    public function testSimpleLoadWithBinaryName(): void
    {
        $php = $this->getPhpBinary();
        $imp = Implementation::load('php');
        $this->assertInstanceOf(Implementation::class, $imp);

        $this->assertEquals($php, $imp->getProcessBuilderFactory()->getBinary());
    }

    public function testMultipleLoadWithBinaryName(): void
    {
        $php = $this->getPhpBinary();
        $imp = Implementation::load(array('bachibouzouk', 'php'));
        $this->assertInstanceOf(Implementation::class, $imp);

        $this->assertEquals($php, $imp->getProcessBuilderFactory()->getBinary());
    }

    public function testLoadWithMultiplePathExpectingAFailure(): void
    {
        $this->expectException(ExecutableNotFoundException::class);

        Implementation::load(array('bachibouzouk', 'moribon'));
    }

    public function testLoadWithUniquePathExpectingAFailure(): void
    {
        $this->expectException(ExecutableNotFoundException::class);

        Implementation::load('bachibouzouk');
    }

    public function testLoadWithCustomLogger(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $imp = Implementation::load('php', $logger);

        $this->assertEquals($logger, $imp->getProcessRunner()->getLogger());
    }

    public function testLoadWithCustomConfigurationAsArray(): void
    {
        $conf = array('timeout' => 200);
        $imp = Implementation::load('php', null, $conf);

        $this->assertEquals($conf, $imp->getConfiguration()->all());
    }

    public function testLoadWithCustomConfigurationAsObject(): void
    {
        $conf = $this->createMock(ConfigurationInterface::class);
        $imp = Implementation::load('php', null, $conf);

        $this->assertEquals($conf, $imp->getConfiguration());
    }

    public function testProcessBuilderFactoryGetterAndSetters(): void
    {
        $imp = Implementation::load('php');
        $factory = $this->createMock(ProcessBuilderFactoryInterface::class);

        $imp->setProcessBuilderFactory($factory);
        $this->assertEquals($factory, $imp->getProcessBuilderFactory());
    }

    public function testConfigurationGetterAndSetters(): void
    {
        $imp = Implementation::load('php');
        $conf = $this->createMock(ConfigurationInterface::class);

        $imp->setConfiguration($conf);
        $this->assertEquals($conf, $imp->getConfiguration());
    }

    public function testTimeoutIsSetOnConstruction(): void
    {
        $imp = Implementation::load('php', null, array('timeout' => 42));
        $this->assertEquals(42, $imp->getProcessBuilderFactory()->getTimeout());
    }

    public function testTimeoutIsSetOnConfigurationSetting(): void
    {
        $imp = Implementation::load('php', null);
        $imp->setConfiguration(new Configuration(array('timeout' => 42)));
        $this->assertEquals(42, $imp->getProcessBuilderFactory()->getTimeout());
    }

    public function testTimeoutIsSetOnProcessBuilderSetting(): void
    {
        $imp = Implementation::load('php', null, array('timeout' => 42));

        $factory = $this->createMock(ProcessBuilderFactoryInterface::class);
        $factory->expects($this->once())
            ->method('setTimeout')
            ->with(42);

        $imp->setProcessBuilderFactory($factory);
    }

    public function testListenRegistersAListener(): void
    {
        $imp = Implementation::load('php');

        $listeners = $this->getMockBuilder(Listeners::class)
            ->disableOriginalConstructor()
            ->getMock();

        $listener = $this->createMock(ListenerInterface::class);

        $listeners->expects($this->once())
            ->method('register')
            ->with($this->equalTo($listener), $this->equalTo($imp));

        $reflexion = new \ReflectionClass(AbstractBinary::class);
        $prop = $reflexion->getProperty('listenersManager');
        $prop->setAccessible(true);
        $prop->setValue($imp, $listeners);

        $imp->listen($listener);
    }

    /**
     * @dataProvider provideCommandParameters
     */
    public function testCommandRunsAProcess($parameters, $bypassErrors, $expectedParameters, $output): void
    {
        $imp = Implementation::load('php');
        $factory = $this->createMock(ProcessBuilderFactoryInterface::class);
        $processRunner = $this->createMock(ProcessRunnerInterface::class);

        $process = $this->getMockBuilder(Process::class)
            ->disableOriginalConstructor()
            ->getMock();

        $processRunner->expects($this->once())
            ->method('run')
            ->with($this->equalTo($process), $this->isInstanceOf('SplObjectStorage'), $this->equalTo($bypassErrors))
            ->willReturn($output);

        $factory->expects($this->once())
            ->method('create')
            ->with($expectedParameters)
            ->willReturn($process);

        $imp->setProcessBuilderFactory($factory);
        $imp->setProcessRunner($processRunner);

        $this->assertEquals($output, $imp->command($parameters, $bypassErrors));
    }

    /**
     * @dataProvider provideCommandWithListenersParameters
     */
    public function testCommandWithTemporaryListeners($parameters, $bypassErrors, $expectedParameters, $output, $count, $listeners): void
    {
        $imp = Implementation::load('php');
        $factory = $this->createMock(ProcessBuilderFactoryInterface::class);
        $processRunner = $this->createMock(ProcessRunnerInterface::class);

        $process = $this->getMockBuilder(Process::class)
            ->disableOriginalConstructor()
            ->getMock();

        $firstStorage = $secondStorage = null;

        $processRunner->expects($this->exactly(2))
            ->method('run')
            ->with($this->equalTo($process), $this->isInstanceOf('SplObjectStorage'), $this->equalTo($bypassErrors))
            ->willReturnCallback(function ($process, $storage, $errors) use ($output, &$firstStorage, &$secondStorage) {
                if (null === $firstStorage) {
                    $firstStorage = $storage;
                } else {
                    $secondStorage = $storage;
                }

                return $output;
            });

        $factory->expects($this->exactly(2))
            ->method('create')
            ->with($expectedParameters)
            ->willReturn($process);

        $imp->setProcessBuilderFactory($factory);
        $imp->setProcessRunner($processRunner);

        $this->assertEquals($output, $imp->command($parameters, $bypassErrors, $listeners));
        $this->assertCount($count, $firstStorage);
        $this->assertEquals($output, $imp->command($parameters, $bypassErrors));
        $this->assertCount(0, $secondStorage);
    }

    public function provideCommandWithListenersParameters(): array
    {
        return array(
            array('-a', false, array('-a'), 'loubda', 2, array($this->getMockListener(), $this->getMockListener())),
            array('-a', false, array('-a'), 'loubda', 1, array($this->getMockListener())),
            array('-a', false, array('-a'), 'loubda', 1, $this->getMockListener()),
            array('-a', false, array('-a'), 'loubda', 0, array()),
        );
    }

    public function provideCommandParameters(): array
    {
        return array(
            array('-a', false, array('-a'), 'loubda'),
            array('-a', true, array('-a'), 'loubda'),
            array('-a -b', false, array('-a -b'), 'loubda'),
            array(array('-a'), false, array('-a'), 'loubda'),
            array(array('-a'), true, array('-a'), 'loubda'),
            array(array('-a', '-b'), false, array('-a', '-b'), 'loubda'),
        );
    }

    public function testUnlistenUnregistersAListener(): void
    {
        $imp = Implementation::load('php');

        $listeners = $this->getMockBuilder(Listeners::class)
            ->disableOriginalConstructor()
            ->getMock();

        $listener = $this->createMock(ListenerInterface::class);

        $listeners->expects($this->once())
            ->method('unregister')
            ->with($this->equalTo($listener), $this->equalTo($imp));

        $reflexion = new \ReflectionClass(AbstractBinary::class);
        $prop = $reflexion->getProperty('listenersManager');
        $prop->setAccessible(true);
        $prop->setValue($imp, $listeners);

        $imp->unlisten($listener);
    }

    /**
     */
    private function getMockListener(): MockObject
    {
        $listener = $this->createMock(ListenerInterface::class);
        $listener
            ->method('forwardedEvents')
            ->willReturn(array());

        return $listener;
    }
}

class Implementation extends AbstractBinary
{
    public function getName(): string
    {
        return 'Implementation';
    }
}
