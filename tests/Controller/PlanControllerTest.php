<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class PlanControllerTest extends WebTestCase
{
    public function testGetPlansReturnsSortedList(): void
    {
        $client = static::createClient();

        $client->request('GET', '/api/plans');

        // Vérifie le code HTTP
        $this->assertResponseIsSuccessful();
        $this->assertResponseStatusCodeSame(200);

        // Récupère et décode la réponse JSON
        $responseData = json_decode($client->getResponse()->getContent(), true);

        // Vérifie qu'on a un tableau
        $this->assertIsArray($responseData);
        $this->assertCount(3, $responseData);

        // Vérifie l'ordre (Free < Premium < Pro)
        $this->assertEquals('Free', $responseData[0]['name']);
        $this->assertEquals('Premium', $responseData[1]['name']);
        $this->assertEquals('Pro', $responseData[2]['name']);

        // Vérifie quelques valeurs de champs pour le premier plan (Free)
        $this->assertEquals(0, $responseData[0]['priceCents']);
        $this->assertEquals(3, $responseData[0]['maxForms']);
        $this->assertEquals(500, $responseData[0]['maxSubmissionsPerMonth']);
    }
}
