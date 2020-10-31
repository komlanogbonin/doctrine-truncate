<?php

namespace Kml\DoctrineTruncateBundle\Service;


use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\Driver\Exception;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Finder\Finder;

/**
 * Class Truncate generate slug from text string
 *
 * @package Kml\DoctrineTruncateBundle\Service
 */
class Truncate
{
    /**
     * @var array
     */
    private $config;

    /**
     * If true all entity tables will truncated
     *
     * @var bool
     */
    private $optionAll;

    /**
     * @var \Doctrine\DBAL\Connection
     */
    private $connection;

    /**
     * @var EntityManager
     */
    private $entityManager;

    /**
     * @var int
     */
    private $entitiesCount;

    /**
     * @var array
     */
    private $queries;

    /**
     * ignore foreign key constraint
     * @var bool
     */
    private $optionIgnoreFk;

    /**
     * @var SymfonyStyle
     */
    private $consoleInputOutput;

    /**
     * Truncate constructor.
     *
     * @param EntityManager $entityManager
     * @param array $config
     */
    public function __construct(EntityManagerInterface $entityManager, array $config)
    {
        $this->config = $config;
        $this->optionAll = false;
        $this->entityManager = $entityManager;
        $this->connection = $entityManager->getConnection();
        $this->queries = [];
        $this->optionIgnoreFk = false;

    }

    /**
     * @param bool $optionIgnoreFk
     * @return Truncate
     */
    public function setOptionIgnoreFk(bool $optionIgnoreFk): Truncate
    {
        $this->optionIgnoreFk = $optionIgnoreFk;
        return $this;
    }

    /**
     * @return array
     */
    public function getConfig()
    {
        return $this->config;
    }

    /**
     * @param array $config
     * @return Truncate
     */
    public function setConfig($config)
    {
        $this->config = $config;
        return $this;
    }

    /**
     * @param bool $optionAll
     * @return Truncate
     */
    public function setOptionAll($optionAll)
    {
        $this->optionAll = $optionAll;
        return $this;
    }

    /**
     * Add additional entity path to the $config
     *
     * @param $entityPath
     */
    public function addEntityNamespace($entityNamespace): void
    {
        $this->config['entityNamespaces'][] = $entityNamespace;
    }

    /**
     * Use this setter to set SymfonyStyle if you want to show debug information on console in context of symfony
     * command.
     *
     * @param SymfonyStyle $consoleInputOutput
     * @return Truncate
     */
    public function setConsoleInputOutput(SymfonyStyle $consoleInputOutput): Truncate
    {
        $this->consoleInputOutput = $consoleInputOutput;
        return $this;
    }

    /**
     * @return int
     */
    public function getEntitiesCount()
    {
        return $this->entitiesCount;
    }

    /**
     * @param int $entitiesCount
     * @return Truncate
     */
    public function setEntitiesCount($entitiesCount)
    {
        $this->entitiesCount = $entitiesCount;
        return $this;
    }

    /**
     * @param string|null $entity
     * @throws \Doctrine\DBAL\DBALException
     */
    public function truncate(?string $entity = null): void
    {
        $foreignKeyString = 'SET FOREIGN_KEY_CHECKS = %d;';
        if ($this->optionIgnoreFk) {
            $this->connection->prepare(sprintf($foreignKeyString, 0))->execute();
        }
//        dd($entity);
        if ($this->optionAll || null === $entity) {
            $this->truncateAll();
        } else {
            if (null !== $entity) {
                $this->truncateTable($entity);
            } else {
                throw new \Exception('You must provide entity full namespace or set optionAll to true');
            }
        }

        if ($this->optionIgnoreFk) {
            $this->connection->prepare(sprintf($foreignKeyString, 1))->execute();
        }
    }

    /**
     * Truncate all tables in database
     */
    public function truncateAll()
    {
        $classes = $this->getClasses();

        foreach ($classes as $class) {
            /*Ignore Interface*/
            if (!$this->inIgnore($class)) {
                $this->truncateTable($class);
            }
        }
    }

    /**
     * Load files array from $entityNamespaces
     *
     * @return array
     * @throws \Exception
     */
    public function getClasses(): array
    {
        $files = [];
        
        foreach ($this->config['entityNamespaces'] as $entityNamespace) {
            $mergedArray = array_merge(
                $files,
                ClassFinder::getClassesInNamespace($entityNamespace, ClassFinder::RECURSIVE_MODE) ?: []
            );
            $files = $mergedArray;
        }
        $this->entitiesCount = count($files);
        
        return $files;
    }

    /**
     * @param $entity
     * @return bool|int
     */
    private function inIgnore($entity)
    {
        return (bool)strpos($entity, 'Interface');
    }

    /**
     * @param string $entity entity short class Name
     */
    protected function truncateTable($entity)
    {
//        try {
            $table = $this->entityManager->getClassMetadata(sprintf('%s', $entity))->getTableName();
            $this->writeln(sprintf('Truncating: %s', $table));
            $command = sprintf('TRUNCATE TABLE %s; ALTER TABLE %s AUTO_INCREMENT = 0;', $table, $table);
            if($this->optionIgnoreFk) {
                $command = sprintf('SET FOREIGN_KEY_CHECKS = 0;
                TRUNCATE TABLE %s; ALTER TABLE %s AUTO_INCREMENT = 0;
                SET FOREIGN_KEY_CHECKS = 1;',
                    $table, $table);
            }
            $this->connection->prepare($command)->execute()
            ;
//        } catch (\Exception $e) {
//            $this->writeln(sprintf("Ignoring %s", $entity));
//        };
    }

    private function writeln($str)
    {
        if ($this->consoleInputOutput) {
            $this->consoleInputOutput->writeln($str);
        }
    }
}