<?php

declare(strict_types=1);

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

return static function (ContainerInterface $container): EntityManagerInterface {
    return $container->get('doctrine.orm.entity_manager');
}; 