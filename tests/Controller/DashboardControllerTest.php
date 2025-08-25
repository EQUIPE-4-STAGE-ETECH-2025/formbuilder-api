<?php

namespace App\Tests\Controller;

use App\Dto\DashboardStatsDto;
use App\Entity\User;
use App\Service\DashboardService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class DashboardControllerTest extends WebTestCase
{
    private $client;
    private EntityManagerInterface $em;
    private MockObject $dashboardService;

    /**
     * @throws Exception
     */
    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);

        $this->dashboardService = $this->createMock(DashboardService::class);
        static::getContainer()->set(DashboardService::class, $this->dashboardService);
    }

    public function testStats(): void
    {
        $user = new User();
        $user->setFirstName('John');
        $user->setLastName('Doe');
        $user->setEmail('user@example.com');
        $user->setPasswordHash(password_hash('secret', PASSWORD_BCRYPT));
        $user->setRole('USER');

        $this->em->persist($user);
        $this->em->flush();

        $statsDto = new DashboardStatsDto(
            totalForms: 5,
            publishedForms: 3,
            totalSubmissions: 10
        );

        $this->dashboardService
            ->expects($this->once())
            ->method('getUserStats')
            ->with($this->equalTo($user))
            ->willReturn($statsDto);

        $this->client->loginUser($user);
        $this->client->request('GET', '/api/dashboard/stats');

        $response = $this->client->getResponse();
        $this->assertResponseIsSuccessful();
        $this->assertJsonStringEqualsJsonString(
            json_encode($statsDto->toArray()),
            $response->getContent()
        );

        $this->em->remove($user);
        $this->em->flush();
    }
}
