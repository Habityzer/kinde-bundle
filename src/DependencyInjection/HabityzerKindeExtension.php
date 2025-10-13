<?php

namespace Habityzer\KindeBundle\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

/**
 * Extension for Habityzer Kinde Bundle
 */
class HabityzerKindeExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        // Register configuration as parameters
        $container->setParameter('habityzer_kinde.domain', $config['domain']);
        $container->setParameter('habityzer_kinde.client_id', $config['client_id']);
        $container->setParameter('habityzer_kinde.client_secret', $config['client_secret']);
        $container->setParameter('habityzer_kinde.webhook_secret', $config['webhook_secret']);
        $container->setParameter('habityzer_kinde.jwks_cache_ttl', $config['jwks_cache_ttl']);
        $container->setParameter('habityzer_kinde.enable_webhook_route', $config['enable_webhook_route']);

        // Load services
        $loader = new YamlFileLoader($container, new FileLocator(__DIR__.'/../../config'));
        $loader->load('services.yaml');
    }
}

