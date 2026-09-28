<?php

declare(strict_types=1);

namespace Alchemy\AclBundle\Tests;

use Alchemy\AclBundle\Admin\PermissionView;
use Alchemy\AclBundle\AlchemyAclBundle;
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
use Alchemy\AclBundle\Tests\Mock\ObjectMock;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;

class AlchemyAclBundleTest extends TestCase
{
    public function testExtensionAlias(): void
    {
        $this->assertSame('alchemy_acl', (new AlchemyAclBundle())->getContainerExtension()->getAlias());
    }

    public function testServicesAreRegistered(): void
    {
        $container = $this->load([
            'objects' => ['pub' => ObjectMock::class],
        ]);

        foreach ([
            DoctrinePermissionRepository::class,
            PermissionManager::class,
            ObjectMapping::class,
            AceSerializer::class,
            PermissionController::class,
            AclObjectDeleteListener::class,
            ObjectTypeFormType::class,
            PermissionView::class,
            AclVoter::class,
            SetPermissionVoter::class,
        ] as $id) {
            $this->assertTrue($container->hasDefinition($id), sprintf('Service "%s" is not registered', $id));
            $this->assertTrue($container->getDefinition($id)->isAutowired(), sprintf('Service "%s" is not autowired', $id));
        }

        $this->assertTrue($container->hasAlias(PermissionRepositoryInterface::class));
        $this->assertSame(DoctrinePermissionRepository::class, (string) $container->getAlias(PermissionRepositoryInterface::class));
    }

    public function testObjectMappingReceivesTheConfiguredObjects(): void
    {
        $container = $this->load([
            'objects' => [
                'pub' => ObjectMock::class,
                'other' => \stdClass::class,
            ],
        ]);

        $this->assertSame(
            ['pub' => ObjectMock::class, 'other' => \stdClass::class],
            $container->getDefinition(ObjectMapping::class)->getArgument('$mapping')
        );
    }

    public function testEveryPermissionIsEnabledByDefault(): void
    {
        $container = $this->load([]);

        $this->assertSame(
            ['VIEW', 'CREATE', 'EDIT', 'DELETE', 'OPERATOR', 'OWNER'],
            $container->getParameter('alchemy_acl.enabled_permissions')
        );
        $this->assertSame(
            '%alchemy_acl.enabled_permissions%',
            $container->getDefinition(PermissionView::class)->getArgument('$enabledPermissions')
        );
    }

    public function testEnabledPermissionsCanBeRestricted(): void
    {
        $container = $this->load([
            'enabled_permissions' => ['VIEW', 'EDIT'],
        ]);

        $this->assertSame(['VIEW', 'EDIT'], $container->getParameter('alchemy_acl.enabled_permissions'));
    }

    public function testEmptyEnabledPermissionsBecomesNull(): void
    {
        $container = $this->load([
            'enabled_permissions' => [],
        ]);

        $this->assertNull($container->getParameter('alchemy_acl.enabled_permissions'));
    }

    public function testUnknownOptionsAreRejected(): void
    {
        $this->expectException(InvalidConfigurationException::class);

        $this->load(['unknown' => true]);
    }

    public function testEasyAdminConfigIsPrependedWhenTheBundleIsInstalled(): void
    {
        $container = $this->createContainer(['EasyAdminBundle' => 'EasyCorp\Bundle\EasyAdminBundle\EasyAdminBundle']);

        $extension = (new AlchemyAclBundle())->getContainerExtension();
        $this->assertInstanceOf(PrependExtensionInterface::class, $extension);
        $extension->prepend($container);

        $configs = $container->getExtensionConfig('easy_admin');
        $this->assertCount(1, $configs);
        $this->assertArrayHasKey('AccessControlEntry', $configs[0]['entities']);
        $this->assertSame('Alchemy\AclBundle\Entity\AccessControlEntry', $configs[0]['entities']['AccessControlEntry']['class']);
    }

    public function testNothingIsPrependedWithoutEasyAdmin(): void
    {
        $container = $this->createContainer([]);

        (new AlchemyAclBundle())->getContainerExtension()->prepend($container);

        $this->assertSame([], $container->getExtensionConfig('easy_admin'));
    }

    private function load(array $config): ContainerBuilder
    {
        $container = $this->createContainer([]);
        (new AlchemyAclBundle())->getContainerExtension()->load([$config], $container);

        return $container;
    }

    private function createContainer(array $bundles): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.environment', 'test');
        $container->setParameter('kernel.build_dir', sys_get_temp_dir());
        $container->setParameter('kernel.bundles', $bundles);

        return $container;
    }
}
