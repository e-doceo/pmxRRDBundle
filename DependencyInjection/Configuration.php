<?php

namespace Pmx\Bundle\RrdBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * This is the class that validates and merges configuration from your app/config files
 *
 * To learn more see {@link http://symfony.com/doc/current/cookbook/bundles/extension.html#cookbook-bundles-extension-config-class}
 */
class Configuration implements ConfigurationInterface
{
    /**
     * {@inheritDoc}
     */
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder();
        $rootNode = $treeBuilder->root('pmx_rrd');

        $rootNode
            ->children()
                ->scalarNode('database_location')->defaultValue('%kernel.root_dir%/rrd')->end()
                ->scalarNode('graph_location')->defaultValue('/rrd')->end()
                ->scalarNode('web_root')->defaultValue('%kernel.root_dir%/../web')->end()
            ->end()
        ;

        return $treeBuilder;
    }
}
