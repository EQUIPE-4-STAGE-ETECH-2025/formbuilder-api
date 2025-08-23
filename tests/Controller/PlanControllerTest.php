<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class PlanControllerTest extends WebTestCase
{
    public function testGetPlansReturnsSortedList(): void
    {
        $client = static::createClient();

        $client->request('GET', '/api/plans');

        $this->assertResponseIsSuccessful();
        $this->assertResponseStatusCodeSame(200);

        $responseData = json_decode($client->getResponse()->getContent(), true);

        $this->assertIsArray($responseData);
        $this->assertGreaterThanOrEqual(3, count($responseData), 'Il doit y avoir au moins 3 plans');

        // Vérifie l'ordre attendu sur les 3 premiers
        $this->assertEquals('Free', $responseData[0]['name']);
        $this->assertEquals('Premium', $responseData[1]['name']);
        $this->assertEquals('Pro', $responseData[2]['name']);

        // Vérifie les champs du premier plan
        $this->assertEquals(0, $responseData[0]['priceCents']);
        $this->assertEquals(3, $responseData[0]['maxForms']);
        $this->assertEquals(500, $responseData[0]['maxSubmissionsPerMonth']);
    }
}
