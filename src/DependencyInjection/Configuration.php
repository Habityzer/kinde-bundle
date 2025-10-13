<?php

namespace Habityzer\KindeBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * Configuration for Habityzer Kinde Bundle
 */
class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('habityzer_kinde');

        $treeBuilder->getRootNode()
            ->children()
                ->scalarNode('domain')
                    ->isRequired()
                    ->cannotBeEmpty()
                    ->info('Kinde domain (e.g., your-business.kinde.com)')
                ->end()
                ->scalarNode('client_id')
                    ->isRequired()
                    ->cannotBeEmpty()
                    ->info('Kinde application client ID')
                ->end()
                ->scalarNode('client_secret')
                    ->defaultValue('')
                    ->info('Kinde application client secret (optional for frontend-only auth)')
                ->end()
                ->scalarNode('webhook_secret')
                    ->defaultValue('')
                    ->info('Webhook secret for signature verification')
                ->end()
                ->integerNode('jwks_cache_ttl')
                    ->defaultValue(3600)
                    ->min(60)
                    ->info('JWKS cache time-to-live in seconds (default: 1 hour)')
                ->end()
                ->booleanNode('enable_webhook_route')
                    ->defaultTrue()
                    ->info('Automatically register webhook route at /api/webhooks/kinde')
                ->end()
            ->end()
        ;

        return $treeBuilder;
    }
}

