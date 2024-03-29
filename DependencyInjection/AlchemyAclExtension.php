<?php

namespace Alchemy\AclBundle\DependencyInjection;

use Alchemy\AclBundle\Mapping\ObjectMapping;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;
use Symfony\Component\Yaml\Parser;

/**
 * This is the class that loads and manages your bundle configuration.
 *
 * To learn more see {@link http://symfony.com/doc/current/cookbook/bundles/extension.html}
 */
class AlchemyAclExtension extends Extension implements PrependExtensionInterface
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $loader = new Loader\YamlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
        $loader->load('services.yaml');

        $mapperDef = $container->findDefinition(ObjectMapping::class);
        $mapperDef->setArgument('$mapping', $config['objects']);

        $container->setParameter('alchemy_acl.enabled_permissions', !empty($config['enabled_permissions']) ? $config['enabled_permissions'] : null);
    }

    public function prepend(ContainerBuilder $container): void
    {
        $bundles = $container->getParameter('kernel.bundles');

        if (isset($bundles['EasyAdminBundle'])) {
            $data = (new Parser())->parse(file_get_contents(__DIR__.'/../Resources/config/easy_admin_entities.yaml'));
            $container->prependExtensionConfig('easy_admin', $data['easy_admin']);
        }
    }
}
