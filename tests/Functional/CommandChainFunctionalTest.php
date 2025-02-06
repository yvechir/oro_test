<?php

namespace App\Tests\Functional;

use App\BarBundle\Command\BarCommand;
use App\ChainCommandBundle\EventListener\CommandChainListener;
use App\FooBundle\Command\FooCommand;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Event\ConsoleTerminateEvent;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * Functional tests for command chaining.
 */
class CommandChainFunctionalTest extends KernelTestCase
{
	private Application $application;
	private EventDispatcher $dispatcher;
	private CommandChainListener $subscriber;

	/**
	 * Sets up the test environment, initializes the console application,
	 * and registers commands and event listeners.
	 */
	protected function setUp(): void
	{
		self::bootKernel();
		$container = self::getContainer();

		/** @var LoggerInterface $logger */
		$logger = $container->get('monolog.logger.command_chain');

		$this->application = new Application();
		$this->application->add(new FooCommand($logger));
		$this->application->add(new BarCommand($logger));

		/** @var CommandChainListener $subscriber */
		$this->subscriber = $container->get(CommandChainListener::class);

		// Create an event dispatcher and register the command chain listener
		$this->dispatcher = new EventDispatcher();
		$this->dispatcher->addSubscriber($this->subscriber);
		$this->application->setDispatcher($this->dispatcher);
	}

	/**
	 * Tests that executing foo:hello triggers bar:hi as expected.
	 */
	public function testFooCommandTriggersBarCommand(): void
	{
		$commandTester = $this->executeCommand('foo:hello');
		$output = $commandTester->getDisplay();

		echo $output;

		$this->assertStringContainsString('Hello from Foo!', $output);
		$this->assertStringContainsString('Hi from Bar!', $output);
	}

	/**
	 * Tests that bar:hi cannot be executed independently and throws an error.
	 */
	public function testBarCommandCannotBeExecutedIndependently(): void
	{
		$commandTester = $this->executeCommandWithCatch('bar:hi');
		$output = trim($commandTester->getDisplay());

		echo $output;

		$expectedError = 'Error: bar:hi command is a member of foo:hello command chain and cannot be executed on its own.';
		$this->assertStringContainsString($expectedError, $output);

		$this->assertSame(Command::FAILURE, $commandTester->getStatusCode());
	}

	/**
	 * Executes a command and catches RuntimeException to capture its output.
	 *
	 * This method is used to test cases where commands should not be executed independently.
	 *
	 * @param string $commandName The command to execute.
	 * @return CommandTester The command tester instance.
	 */
	private function executeCommandWithCatch(string $commandName): CommandTester
	{
		$command = $this->application->find($commandName);
		$commandTester = new CommandTester($command);

		try {
			$commandTester->execute([]);
		} catch (\RuntimeException $e) {
			// Catching expected RuntimeException for invalid command execution.
		}

		return $commandTester;
	}

	/**
	 * Executes a command and dispatches the ConsoleTerminateEvent to trigger chained commands.
	 *
	 * This method ensures that the master command executes its chain members correctly.
	 *
	 * @param string $commandName The command to execute.
	 * @return CommandTester The command tester instance.
	 */
	private function executeCommand(string $commandName): CommandTester
	{
		$command = $this->application->find($commandName);
		$commandTester = new CommandTester($command);
		$commandTester->execute([]);

		// Dispatch the ConsoleTerminateEvent to ensure chain execution.
		$event = new ConsoleTerminateEvent(
			$command,
			$commandTester->getInput(),
			$commandTester->getOutput(),
			0
		);
		$this->dispatcher->dispatch($event);

		return $commandTester;
	}
}
