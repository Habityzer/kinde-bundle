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
                    ->defaultValue('your-business.kinde.com')
                    ->info('Kinde domain (e.g., your-business.kinde.com). REQUIRED: Set this in your .env file')
                ->end()
                ->scalarNode('client_id')
                    ->defaultValue('your-kinde-client-id')
                    ->info('Kinde application client ID. REQUIRED: Set this in your .env file')
                ->end()
                // variableNode, not arrayNode: a prototyped array node refuses env placeholders,
                // and this list is meant to come from '%env(csv:KINDE_ALLOWED_CLIENT_IDS)%'.
                ->variableNode('allowed_client_ids')
                    ->info('Additional Kinde application client IDs whose access tokens are accepted (e.g. a native mobile app in the same Kinde business). Empty = only client_id. List or %env(csv:...)%; blank entries are ignored')
                    ->defaultValue([])
                    ->validate()
                        ->ifTrue(static fn ($value) => !\is_array($value) && !\is_string($value))
                        ->thenInvalid('allowed_client_ids must be a list of client IDs (or an env(csv:...) placeholder), got %s')
                    ->end()
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

