<?php

declare(strict_types=1);

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

return static function (ContainerInterface $container): ObjectManager {
    /** @var EntityManagerInterface $entityManager */
    $entityManager = $container->get('doctrine.orm.entity_manager');
    return $entityManager;
}; 