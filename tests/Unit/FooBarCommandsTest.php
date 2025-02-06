<?php

namespace App\Tests\Unit;

use App\BarBundle\Command\BarCommand;
use App\FooBundle\Command\FooCommand;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

/**
 * Unit tests for FooCommand and BarCommand.
 */
class FooBarCommandsTest extends TestCase
{
	private Application $application;
	private LoggerInterface $logger;

	/**
	 * Sets up the test environment, initializes the console application,
	 * and registers the required commands.
	 */
	protected function setUp(): void
	{
		$this->logger = $this->createMock(LoggerInterface::class);
		$this->application = new Application();

		// Register commands in the console application
		$this->application->add(new FooCommand($this->logger));
		$this->application->add(new BarCommand($this->logger));
	}

	/**
	 * Tests that executing foo:hello triggers bar:hi as expected.
	 */
	public function testFooCommandExecutesBarCommand(): void
	{
		$fooCommand = $this->application->find('foo:hello');
		$output = new BufferedOutput();

		// Execute the master command
		$fooCommand->run(new ArrayInput([]), $output);

		// Execute the chained command manually
		$barCommand = $this->application->find('bar:hi');
		$barCommand->run(new ArrayInput(['--from-master' => true]), $output);

		$outputText = $output->fetch();

		$this->assertStringContainsString('Hello from Foo!', $outputText);
		$this->assertStringContainsString('Hi from Bar!', $outputText);
	}

	/**
	 * Tests that bar:hi cannot be executed independently and throws an error.
	 */
	public function testBarCommandCannotBeExecutedIndependently(): void
	{
		$barCommand = $this->application->find('bar:hi');
		$output = new BufferedOutput();

		// Run the bar:hi command without the --from-master flag
		$barCommand->run(new ArrayInput([]), $output);
		$outputText = trim($output->fetch());

		// Expected error message
		$expectedError = 'Error: bar:hi command is a member of foo:hello command chain and cannot be executed on its own.';
		$this->assertStringContainsString($expectedError, $outputText);
	}
}
