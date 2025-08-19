<?php

namespace App\Tests\Controller;

use App\Dto\AdminStatsDto;
use App\Dto\UserListDto;
use App\Entity\User;
use App\Service\AdminService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\Exception;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class AdminControllerTest extends WebTestCase
{
    private $client;
    private EntityManagerInterface $em;
    private AdminService $adminService;

    /**
     * @throws Exception
     */
    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);

        $this->adminService = $this->createMock(AdminService::class);
        static::getContainer()->set(AdminService::class, $this->adminService);
    }

    public function testListUsers(): void
    {
        $user = new User();
        $user->setFirstName('Jane');
        $user->setLastName('Doe');
        $user->setEmail('admin@example.com');
        $user->setPasswordHash(password_hash('secret', PASSWORD_BCRYPT));
        $user->setRole('ADMIN');

        $this->em->persist($user);
        $this->em->flush();

        $userListDto = new UserListDto($user, 'Free Plan', 2, 5);

        $this->adminService
            ->expects($this->once())
            ->method('listUsers')
            ->willReturn([$userListDto]);

        $this->client->loginUser($user);
        $this->client->request('GET', '/api/admin/users');

        $response = $this->client->getResponse();
        $this->assertResponseIsSuccessful();
        $this->assertJsonStringEqualsJsonString(
            json_encode([$userListDto->toArray()]),
            $response->getContent()
        );

        $this->em->remove($user);
        $this->em->flush();
    }

    public function testStats(): void
    {
        $user = new User();
        $user->setFirstName('Jane');
        $user->setLastName('Doe');
        $user->setEmail('admin@example.com');
        $user->setPasswordHash(password_hash('secret', PASSWORD_BCRYPT));
        $user->setRole('ADMIN');

        $this->em->persist($user);
        $this->em->flush();

        $statsDto = new AdminStatsDto(
            totalUsers: 10,
            totalForms: 20,
            totalSubmissions: 50,
            usersPerMonth: [],
            totalUsersPerMonth: [],
            usersByPlan: [],
            recentAuditLogs: []
        );

        $this->adminService
            ->expects($this->once())
            ->method('getStats')
            ->willReturn($statsDto);

        $this->client->loginUser($user);
        $this->client->request('GET', '/api/admin/stats');

        $response = $this->client->getResponse();
        $this->assertResponseIsSuccessful();
        $this->assertJsonStringEqualsJsonString(
            json_encode($statsDto),
            $response->getContent()
        );

        $this->em->remove($user);
        $this->em->flush();
    }
}
