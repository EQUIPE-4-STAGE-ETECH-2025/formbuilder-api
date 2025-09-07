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

        // Vérifie les champs du premier plan (Free)
        $freePlan = $responseData[0];
        $this->assertEquals(0, $freePlan['priceCents']);
        $this->assertEquals(3, $freePlan['maxForms']);
        $this->assertEquals(500, $freePlan['maxSubmissionsPerMonth']);
        $this->assertEquals(10, $freePlan['maxStorageMb']);

        // Vérifie les nouveaux champs
        $this->assertArrayHasKey('stripeProductId', $freePlan);
        $this->assertArrayHasKey('stripePriceId', $freePlan);
        $this->assertArrayHasKey('features', $freePlan);

        // Vérifie les valeurs spécifiques pour le plan Free
        $this->assertIsArray($freePlan['features']);

        // Vérifie les champs du plan Premium
        $premiumPlan = $responseData[1];
        $this->assertEquals(2900, $premiumPlan['priceCents']);

        // Vérifie les champs du plan Pro
        $proPlan = $responseData[2];
        $this->assertEquals(9900, $proPlan['priceCents']);
        $this->assertEquals(-1, $proPlan['maxForms']); // Illimité
    }
}
