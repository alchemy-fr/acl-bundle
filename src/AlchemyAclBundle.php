<?php

declare(strict_types=1);

namespace Alchemy\AclBundle;

use Alchemy\AclBundle\Admin\PermissionView;
use Alchemy\AclBundle\Controller\PermissionController;
use Alchemy\AclBundle\Doctrine\Listener\AclObjectDeleteListener;
use Alchemy\AclBundle\Form\ObjectTypeFormType;
use Alchemy\AclBundle\Mapping\ObjectMapping;
use Alchemy\AclBundle\Repository\DoctrinePermissionRepository;
use Alchemy\AclBundle\Repository\PermissionRepositoryInterface;
use Alchemy\AclBundle\Security\PermissionManager;
use Alchemy\AclBundle\Security\Voter\AclVoter;
use Alchemy\AclBundle\Security\Voter\SetPermissionVoter;
use Alchemy\AclBundle\Serializer\AceSerializer;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;
use Symfony\Component\Yaml\Parser;

class AlchemyAclBundle extends AbstractBundle
{
    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->children()
                ->arrayNode('enabled_permissions')
                    ->defaultValue([
                        'VIEW',
                        'CREATE',
                        'EDIT',
                        'DELETE',
                        'OPERATOR',
                        'OWNER',
                    ])
                    ->info('Explicit enabled permissions, all are enabled by default.')
                    ->example([
                        'VIEW',
                        'EDIT',
                    ])
                    ->scalarPrototype()->end()
                ->end()
                ->arrayNode('objects')
                ->useAttributeAsKey('key')
                    ->prototype('scalar')
                ->end()
            ->end()
        ;
    }

    public function prependExtension(ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $bundles = $builder->getParameter('kernel.bundles');

        if (isset($bundles['EasyAdminBundle'])) {
            $data = (new Parser())->parse(file_get_contents(__DIR__.'/../config/easy_admin_entities.yaml'));
            $builder->prependExtensionConfig('easy_admin', $data['easy_admin']);
        }
    }

    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $services = $container->services();
        $services
            ->defaults()
                ->autowire()
                ->autoconfigure();

        $services->set(DoctrinePermissionRepository::class);
        $services->set(PermissionManager::class);
        $services->set(ObjectMapping::class)
            ->set('$mapping', $config['objects']);
        $services->set(AceSerializer::class);
        $services->set(PermissionController::class);
        $services->set(AclObjectDeleteListener::class);
        $services->set(ObjectTypeFormType::class);
        $services->set(PermissionView::class)
            ->arg('$enabledPermissions', '%alchemy_acl.enabled_permissions%');

        $services->alias(PermissionRepositoryInterface::class, DoctrinePermissionRepository::class);

        $services->set(AclVoter::class);
        $services->set(SetPermissionVoter::class);

        $container->parameters()
            ->set('alchemy_acl.enabled_permissions', !empty($config['enabled_permissions']) ? $config['enabled_permissions'] : null);
    }
}
