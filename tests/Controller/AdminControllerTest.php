<?php

namespace App\Tests\Controller;

use App\Entity\User;
use App\Service\AdminService;
use PHPUnit\Framework\MockObject\Exception;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class AdminControllerTest extends WebTestCase
{
    private $client;
    private $adminService;

    /**
     * @throws Exception
     */
    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->adminService = $this->createMock(AdminService::class);
        self::getContainer()->set(AdminService::class, $this->adminService);
    }

    public function testListUsers(): void
    {
        $user1 = (new User())
            ->setEmail('user1@example.com')
            ->setFirstName('User')
            ->setLastName('One')
            ->setRole('USER')
            ->setIsEmailVerified(true);

        $user2 = (new User())
            ->setEmail('user2@example.com')
            ->setFirstName('User')
            ->setLastName('Two')
            ->setRole('ADMIN')
            ->setIsEmailVerified(false);

        $this->adminService->expects($this->once())
            ->method('listUsers')
            ->willReturn([$user1, $user2]);

        $this->client->request('GET', '/api/admin/users');

        $this->assertResponseIsSuccessful();
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertCount(2, $data);
        $this->assertEquals('user1@example.com', $data[0]['email']);
    }
}
