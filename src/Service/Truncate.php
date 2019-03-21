<?php

namespace Kml\DoctrineTruncateBundle\Service;//                    ->defaultValue(["App\\Entity"])


use Doctrine\ORM\EntityManager;
use HaydenPierce\ClassFinder\ClassFinder;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Class Truncate
 *
 * Clear doctrine (mysql) database table
 *
 * @package Kml\DoctrineTruncateBundle\Service
 */
class Truncate
{
    /**
     * Truncate configuration
     * @see kml_doctrine_truncate key in .yaml configs
     *
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

    //TODO: for future optimisation, execute all queries in one execution.
//    /**
//     * @var array
//     */
//    private $queries;

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
    public function __construct(EntityManager $entityManager, array $config)
    {
        $this->config = $config;
        $this->optionAll = false;
        $this->entityManager = $entityManager;
        $this->connection = $entityManager->getConnection();
//        $this->queries = [];
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
    public function getConfig(): array
    {
        return $this->config;
    }

    /**
     * @param array $config
     * @return Truncate
     */
    public function setConfig($config): self
    {
        $this->config = $config;
        return $this;
    }

    /**
     * @param bool $optionAll
     * @return Truncate
     */
    public function setOptionAll($optionAll): self
    {
        $this->optionAll = $optionAll;
        return $this;
    }

    /**
     * Add additional entity namespace to the $config
     *
     * @param $entityNamespace
     */
    public function addEntityNamespace($entityNamespace): self
    {
        $this->config['entityNamespaces'][] = $entityNamespace;
        return $this;
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
     * Truncate a single entity
     *
     * @param string|null $entity
     * @throws \Doctrine\DBAL\DBALException
     */
    public function truncate(?string $entity = null): void
    {
        $foreignKeyString = 'SET FOREIGN_KEY_CHECKS = %d;';
        if ($this->optionIgnoreFk) {
            $this->connection->prepare(sprintf($foreignKeyString, 0))->execute();
        }

        if ($this->optionAll || null === $entity) {
            $this->truncateAll();
        } else {
            if (null !== $entity) {
                $this->truncateTable($entity);
            } else {
                throw new \InvalidArgumentException('You must provide entity full namespace or use --all option');
            }
        }

        if ($this->optionIgnoreFk) {
            $this->connection->prepare(sprintf($foreignKeyString, 1))->execute();
        }
    }

    /**
     * Truncate all tables in database
     *
     * @throws \Exception
     */
    public function truncateAll()
    {
        $classes = $this->getClasses();

        foreach ($classes as $class) {
            //process truncate if class not match ignore pattern defined in configs
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
     * Check if a class is ignored. Return true if class
     *
     * @param $entity
     * @return bool|int
     */
    private function inIgnore($entity)
    {
        return (
            ($this->config['ignore']['regex'] !== null && preg_match($this->config['ignore']['regex'], $entity))
            || in_array($entity, $this->config['ignore']['classes'], true)
        );
    }

    /**
     * Truncate table
     *
     * @param string $entity entity short class Name
     */
    protected function truncateTable($entity)
    {
        $entityFullNamespace = $this->getFullNamespace($entity);

        if (!$entityFullNamespace) {
            return;
        }

        try {
            $table = $this->entityManager->getClassMetadata(sprintf('%s', $entityFullNamespace))->getTableName();
            $this->writeln(sprintf('Truncating: %s', $table));
            $this->connection->prepare(sprintf('TRUNCATE TABLE %s; ALTER TABLE %s AUTO_INCREMENT = 0;', $table, $table))
                ->execute()
            ;
        } catch (\Exception $e) {
            $this->writeln(sprintf("Ignoring %s", $entity));
        };
    }

    /**
     * Get entity full namespace.
     * If you specify class name without it namespace, this function will add class namespace.
     * In case of the class name is defined in many namespace, just the first will be used.
     *
     * @example getFullNamespace('Product') return 'App\Entity\Product'
     *
     * @param $entity
     * @return string
     */
    private function getFullNamespace($entity): ?string
    {
        if ($this->inIgnore($entity)) {
            $this->consoleInputOutput->writeln(sprintf('Ignore: %s is excluded', $entity));
            return null;
        }

        if (class_exists($entity)) {
            return $entity;
        }

        $full = null;
        $namespace = '';
        $i = count($this->config['entityNamespaces']);
        while (!class_exists($full = $namespace . '\\' . $entity) && $i > 0) {
            $namespace = $this->config['entityNamespaces'][$i - 1];
            $i--;
        }
        return class_exists($full) ?: null;
    }

    /**
     * Write console output message
     *
     * @param $str
     */
    private function writeln($str): void
    {
        if ($this->consoleInputOutput) {
            $this->consoleInputOutput->writeln($str);
        }
    }
}