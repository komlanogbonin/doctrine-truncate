<?php

namespace Kml\DoctrineTruncateBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * This is the class that validates and merges configuration from your app/config files.
 *
 * To learn more see {@link http://symfony.com/doc/current/cookbook/bundles/configuration.html}
 */
class Configuration implements ConfigurationInterface
{
    /**
     * {@inheritdoc}
     */
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder();
        $treeBuilder->root('kml_doctrine_truncate')
            ->children()
            ->arrayNode('entityNamespaces')
            ->isRequired()
            ->example(' ["App\\Entity","App\\Entity\\Acme"]')
            ->requiresAtLeastOneElement()
            ->scalarPrototype()->end()
            ->end()
            ->arrayNode('ignore')
            ->children()
            ->arrayNode('classes')
            ->defaultValue([])
            ->scalarPrototype()
            ->defaultValue(null)
            ->example(["App\\Entity\\Product"])
            ->end()
            ->end()
            ->scalarNode('regex')
            ->defaultValue(null)
            ->example("/(foo)(bar)(baz)/")
            ->end()
            ->end()
            ->end()
            ->end()
        ;

        return $treeBuilder;
    }
}
