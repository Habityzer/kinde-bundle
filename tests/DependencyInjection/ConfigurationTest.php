<?php

namespace Habityzer\KindeBundle\Tests\DependencyInjection;

use Habityzer\KindeBundle\DependencyInjection\Configuration;
use Habityzer\KindeBundle\DependencyInjection\HabityzerKindeExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class ConfigurationTest extends TestCase
{
    public function testAllowedClientIdsDefaultsToEmptyList(): void
    {
        $config = $this->process([]);

        self::assertSame([], $config['allowed_client_ids']);
    }

    public function testAllowedClientIdsAcceptsAList(): void
    {
        $config = $this->process(['allowed_client_ids' => ['mobile-a', 'mobile-b']]);

        self::assertSame(['mobile-a', 'mobile-b'], $config['allowed_client_ids']);
    }

    public function testBlankEntriesPassConfigurationAndAreDroppedByTheValidator(): void
    {
        // env(csv:X) resolves an empty variable to [null] at runtime, after configuration processing;
        // the configuration therefore stays permissive and KindeTokenValidator normalises the list.
        self::assertSame([null], $this->process(['allowed_client_ids' => [null]])['allowed_client_ids']);
        self::assertSame([''], $this->process(['allowed_client_ids' => ['']])['allowed_client_ids']);
    }

    public function testAStringIsAcceptedBecauseEnvPlaceholdersArriveAsStrings(): void
    {
        $config = $this->process(['allowed_client_ids' => 'env_placeholder_KINDE_ALLOWED_CLIENT_IDS']);

        self::assertSame('env_placeholder_KINDE_ALLOWED_CLIENT_IDS', $config['allowed_client_ids']);
    }

    public function testNonListValuesAreRejected(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('allowed_client_ids must be a list of client IDs');

        $this->process(['allowed_client_ids' => 42]);
    }

    public function testExtensionExposesTheListAsAParameterAndWiresTheValidator(): void
    {
        $container = new ContainerBuilder();
        (new HabityzerKindeExtension())->load([[
            'domain' => 'https://auth.example.test',
            'client_id' => 'web',
            'allowed_client_ids' => ['mobile'],
        ]], $container);

        self::assertSame(['mobile'], $container->getParameter('habityzer_kinde.allowed_client_ids'));

        $arguments = $container->getDefinition('Habityzer\KindeBundle\Service\KindeTokenValidator')->getArguments();
        self::assertSame('%habityzer_kinde.allowed_client_ids%', $arguments['$allowedClientIds']);
    }

    private function process(array $config): array
    {
        return (new Processor())->processConfiguration(new Configuration(), [$config]);
    }
}
