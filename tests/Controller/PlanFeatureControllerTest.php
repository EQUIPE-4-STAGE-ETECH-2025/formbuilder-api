<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class PlanFeatureControllerTest extends WebTestCase
{
    public function testListFeaturesReturnsData()
    {
        $client = static::createClient();


        $planId = '550e8400-e29b-41d4-a716-446655440202';

        $client->request('GET', "/api/plans/$planId/features");

        $this->assertResponseIsSuccessful();
        $this->assertResponseFormatSame('json');

        $data = json_decode($client->getResponse()->getContent(), true);

        $this->assertIsArray($data);
        $this->assertNotEmpty($data);
        $this->assertArrayHasKey('id', $data[0]);
        $this->assertArrayHasKey('code', $data[0]);
        $this->assertArrayHasKey('label', $data[0]);
    }

    public function testListFeaturesReturns404ForInvalidPlan()
    {
        $client = static::createClient();

        $invalidPlanId = '00000000-0000-0000-0000-000000000000';

        $client->request('GET', "/api/plans/$invalidPlanId/features");

        $this->assertResponseStatusCodeSame(404);
    }
}
