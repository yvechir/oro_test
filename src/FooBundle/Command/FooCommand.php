<?php

namespace App\FooBundle\Command;

use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Console command that outputs "Hello from Foo!".
 */
#[AsCommand(
	name: 'foo:hello',
	description: 'Outputs "Hello from Foo!"'
)]
class FooCommand extends Command
{
	private LoggerInterface $logger;

	/**
	 * FooCommand constructor.
	 *
	 * @param LoggerInterface $logger Logger instance for tracking command execution.
	 */
	public function __construct(LoggerInterface $logger)
	{
		parent::__construct();
		$this->logger = $logger;
	}

	/**
	 * Executes the foo:hello command.
	 *
	 * @param InputInterface  $input  The input instance.
	 * @param OutputInterface $output The output instance.
	 *
	 * @return int Command exit status.
	 */
	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		$message = 'Hello from Foo!';
		$output->writeln($message);
		$this->logger->info($message);

		return Command::SUCCESS;
	}
}
