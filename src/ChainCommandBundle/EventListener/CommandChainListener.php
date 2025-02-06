<?php

namespace App\ChainCommandBundle\EventListener;

use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\Console\Event\ConsoleTerminateEvent;
use Symfony\Component\Console\Exception\CommandNotFoundException;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * CommandChainListener.
 *
 * This event listener enables command chaining, allowing one console command to trigger
 * the execution of additional commands in a predefined sequence.
 */
class CommandChainListener implements EventSubscriberInterface
{
	/**
	 * Log mapping.
	 */
	private const LOG_MESSAGES = [
		'master_registered' => '%s is a master command of a command chain that has registered member commands',
		'child_registered' => '%s registered as a member of %s command chain',
		'executing_master' => 'Executing foo:hello command itself first:',
		'executing_children' => 'Executing %s chain members:',
		'execution_completed' => 'Execution of %s chain completed.',
		'command_not_found' => 'Command %s not found.',
		'child_execution_error' => 'Error: %s command is a member of foo:hello command chain and cannot be executed on its own.',
	];

	/**
	 * @var array<string, array<string>> Stores master commands and their respective child commands.
	 */
	private array $commandChain = [];

	/**
	 * @var array<string, bool> Prevents duplicate logging of command registrations.
	 */
	private array $registeredCommands = [];

	public function __construct(
		private LoggerInterface $logger,
		private Application $application
	) {
		$this->initializeCommandChain();
	}

	/**
	 * Subscribes to console events for command execution handling.
	 *
	 * @return array<string, array<int, string>> List of subscribed events and their handler methods.
	 */
	public static function getSubscribedEvents(): array
	{
		return [
			ConsoleCommandEvent::class => ['onConsoleCommand', 10],
			ConsoleTerminateEvent::class => ['onConsoleTerminate', 0],
		];
	}

	/**
	 * Initializes predefined command chains.
	 */
	private function initializeCommandChain(): void
	{
		$this->registerChainCommand('foo:hello', 'bar:hi');
	}

	/**
	 * Registers a child command under a master command.
	 *
	 * @param string $masterCommand The primary command.
	 * @param string $childCommand The dependent command to be executed after the master.
	 */
	public function registerChainCommand(string $masterCommand, string $childCommand): void
	{
		if (!isset($this->commandChain[$masterCommand])) {
			$this->commandChain[$masterCommand] = [];
		}

		if (!in_array($childCommand, $this->commandChain[$masterCommand], true)) {
			$this->commandChain[$masterCommand][] = $childCommand;
		}
	}

	/**
	 * Handles the event when a console command starts executing.
	 *
	 * @param ConsoleCommandEvent $event The console command execution event.
	 */
	public function onConsoleCommand(ConsoleCommandEvent $event): void
	{
		$command = $event->getCommand();
		$commandName = $command->getName();

		if ($this->isMasterCommand($commandName)) {
			$this->handleMasterCommand($commandName);
			return;
		}

		if ($this->isChildCommand($commandName)) {
			$this->handleChildCommand($event, $commandName);
		}
	}

	/**
	 * Handles the event when a console command execution completes.
	 *
	 * @param ConsoleTerminateEvent $event The console command termination event.
	 */
	public function onConsoleTerminate(ConsoleTerminateEvent $event): void
	{
		$command = $event->getCommand();
		$commandName = $command->getName();

		if (!$this->isMasterCommand($commandName) || $event->getExitCode() !== 0) {
			return;
		}

		$this->executeChainedCommands($commandName, $event->getOutput());
	}

	/**
	 * Checks if a command is a master command.
	 *
	 * @param string $commandName The command name.
	 *
	 * @return bool True if the command is a master command, false otherwise.
	 */
	private function isMasterCommand(string $commandName): bool
	{
		return isset($this->commandChain[$commandName]);
	}

	/**
	 * Checks if a command is a child command.
	 *
	 * @param string $commandName The command name.
	 *
	 * @return bool True if the command is a child command, false otherwise.
	 */
	private function isChildCommand(string $commandName): bool
	{
		foreach ($this->commandChain as $childCommands) {
			if (in_array($commandName, $childCommands, true)) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Handles execution logic for master commands.
	 *
	 * @param string $commandName The name of the master command.
	 */
	private function handleMasterCommand(string $commandName): void
	{
		if (!isset($this->registeredCommands[$commandName])) {
			$this->logMasterCommandRegistration($commandName);
			$this->registeredCommands[$commandName] = true;
		}

		$this->logger->info(self::LOG_MESSAGES['executing_master']);
	}

	/**
	 * Prevents standalone execution of child commands.
	 *
	 * @param ConsoleCommandEvent $event The command execution event.
	 * @param string $commandName The name of the child command.
	 */
	private function handleChildCommand(ConsoleCommandEvent $event, string $commandName): void
	{
		$errorMessage = sprintf(self::LOG_MESSAGES['child_execution_error'], $commandName);
		$event->getOutput()->writeln($errorMessage);
		$event->disableCommand();
	}

	/**
	 * Logs the registration of a master command and its child commands.
	 *
	 * @param string $masterCommand The master command name.
	 */
	private function logMasterCommandRegistration(string $masterCommand): void
	{
		$this->logger->info(sprintf(self::LOG_MESSAGES['master_registered'], $masterCommand));

		foreach ($this->commandChain[$masterCommand] as $childCommand) {
			$this->logger->info(sprintf(
				self::LOG_MESSAGES['child_registered'],
				$childCommand,
				$masterCommand
			));
		}
	}

	/**
	 * Executes all chained commands after a master command completes.
	 *
	 * @param string $masterCommand The master command name.
	 * @param mixed $output The command output stream.
	 */
	private function executeChainedCommands(string $masterCommand, $output): void
	{
		$this->logger->info(sprintf(self::LOG_MESSAGES['executing_children'], $masterCommand));

		foreach ($this->commandChain[$masterCommand] as $childCommand) {
			$this->executeChildCommand($childCommand, $output);
		}

		$this->logger->info(sprintf(self::LOG_MESSAGES['execution_completed'], $masterCommand));
	}

	/**
	 * Executes a child command.
	 *
	 * @param string $childCommand The name of the child command.
	 * @param mixed $output The command output stream.
	 */
	private function executeChildCommand(string $childCommand, $output): void
	{
		try {
			$childCommandInstance = $this->application->find($childCommand);
			$childCommandInstance->run(new ArrayInput(['--from-master' => true]), $output);
		} catch (CommandNotFoundException $e) {
			$this->logger->error(sprintf(self::LOG_MESSAGES['command_not_found'], $childCommand));
		}
	}
}
