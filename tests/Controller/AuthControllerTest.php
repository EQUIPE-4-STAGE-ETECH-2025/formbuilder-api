<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use App\Entity\User;
use Symfony\Component\Uid\Uuid;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AuthControllerTest extends WebTestCase
{
    private string $testEmail = 'test@example.com';
    private ?EntityManagerInterface $em = null;

    public function testLoginSuccess(): void
    {
        $client = static::createClient();
        $this->em = static::getContainer()->get('doctrine')->getManager();

        $passwordHasher = static::getContainer()->get(UserPasswordHasherInterface::class);

        $user = new User();
        $user->setId(Uuid::v4());
        $user->setEmail($this->testEmail);
        $user->setFirstName('Jean');
        $user->setLastName('Test');
        $user->setRole('USER');
        $user->setIsEmailVerified(true);
        $user->setPasswordHash($passwordHasher->hashPassword($user, 'password123'));

        $this->em->persist($user);
        $this->em->flush();

        $client->request(
            'POST',
            '/api/auth/login',
            [], 
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'email' => $this->testEmail,
                'password' => 'password123',
            ])
        );
        

        $this->assertResponseIsSuccessful();

        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('token', $data);
        $this->assertEquals($this->testEmail, $data['user']['email']);
    }

    public function testLoginValidationError(): void
    {
        $client = static::createClient();
        $this->em = static::getContainer()->get('doctrine')->getManager();

        $client->request(
            'POST',
            '/api/auth/login',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'email' => '',
                'password' => '',
            ])
        );
        

        $this->assertResponseStatusCodeSame(422);

        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('errors', $data);
        $this->assertArrayHasKey('email', $data['errors']);
        $this->assertArrayHasKey('password', $data['errors']);
    }

    //Fonction qui nettoie l'environnement après le test
    protected function tearDown(): void
    {
        if ($this->em) {
            $user = $this->em->getRepository(User::class)->findOneBy(['email' => $this->testEmail]);
            if ($user) {
                $this->em->remove($user);
                $this->em->flush();
            }
            $this->em->close();
            $this->em = null;
        }

        parent::tearDown();
    }
}
