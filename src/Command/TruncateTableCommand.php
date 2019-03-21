<?php

namespace Kml\DoctrineTruncateBundle\Command;

use Kml\DoctrineTruncateBundle\Service\Truncate;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Class TruncateTableCommand
 *
 * @package Kml\DoctrineTruncateBundle\Command
 */
class TruncateTableCommand extends Command
{
    const TITLE = 'Truncate Doctrine Database Tables from command line';
    const OPTION_IGNORE_FK = 'ignore-fk';
    const OPTION_ALL = 'all';
    const OPTION_NAMESPACE = 'namespace';
    const OPTION_ADD_NAMESPACE = 'add-namespace';
    const ARGUMENT_ENTITY = 'entity';

    /**
     * Command default name
     *
     * @var string
     */
    protected static $defaultName = 'doctrine:truncate';

    /**
     * @var SymfonyStyle
     */
    private $consoleInputOutput;

    /**
     * @var Truncate
     */
    private $truncateService;

    /**
     * TruncateTableCommand constructor.
     * @param $truncateService
     */
    public function __construct(Truncate $truncateService)
    {
        parent::__construct(self::$defaultName);
        $this->truncateService = $truncateService;
    }

    /**
     *
     */
    protected function configure(): void
    {
        $this
            ->setDescription('Clear MySQL data base tables using truncate command')
            ->addArgument(
                self::ARGUMENT_ENTITY,
                InputArgument::OPTIONAL,
                'Entity class name (e.g: App/Entity/MyClass)'
            )
            ->addOption(
                self::OPTION_IGNORE_FK,
                'ignore',
                InputOption::VALUE_OPTIONAL,
                'Ignore Foreign key check. If is set FOREIGN_KEY_CHECKS will be disabled during truncate',
                false
            )
            ->addOption(
                self::OPTION_ALL,
                'a',
                InputOption::VALUE_OPTIONAL,
                'All tables',
                false
            )
            ->addOption(
                self::OPTION_NAMESPACE,
                'ns',
                InputOption::VALUE_OPTIONAL,
                'Define a name space where is your entities',
                []
            )
            ->addOption(
                self::OPTION_ADD_NAMESPACE,
                'add-ns',
                InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY,
                'Define a additional namespaces to your configuration'
            )
            ->setHelp("This command help you to clear your entities table using doctrine query.
We RECOMMANDED USE IN DEV MODE.
Feel free to report any bug, suggestion or new feature.
@examples:
    Truncate your user table: php bin/console doctrine:truncate User 
    Truncate your user table ignoring Foreign key check: php bin/console doctrine:truncate User --ignore-fk
    Truncate all your application tables: php bin/console doctrine:truncate --all or php bin/console doctrine:truncate
        ")
        ;
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int|void|null
     * @throws \Doctrine\DBAL\DBALException
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->consoleInputOutput = new SymfonyStyle($input, $output);
        $this->truncateService->setConsoleInputOutput($this->consoleInputOutput);

        $this->consoleInputOutput->title(self::TITLE);
        $entity = $input->getArgument(self::ARGUMENT_ENTITY);
        $options = $input->getOptions();
        $optionAllEnabled = (null === $entity || $options[self::OPTION_ALL] !== false);

        if (count($options[self::OPTION_NAMESPACE]) > 0) {
            $this->truncateService->addEntityNamespace($entity);
        }

        if (false !== $options[self::OPTION_IGNORE_FK]) {
            $this->consoleInputOutput->writeln(sprintf('Truncating %s with no FOREIGN_KEY_CHECKS', $entity));
            $this->truncateService->setOptionIgnoreFk(true);
        }
        if ($optionAllEnabled) {
            $this->consoleInputOutput->writeln('Truncating ALL database tables');
            $this->truncateService->setOptionAll(true);
        }

        $this->truncateService->truncate($entity);

        $this->consoleInputOutput->writeln('Done !');
    }
}