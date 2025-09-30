<?php

namespace App\Tests\Service;

use App\Entity\Form;
use App\Service\FormEmbedService;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Uid\Uuid;

class FormEmbedServiceTest extends TestCase
{
    /**
     * @throws Exception
     */
    public function testGenerateEmbedCodeSuccess(): void
    {
        $form = new Form();
        $form->setId(Uuid::v4());
        $form->setTitle('Test Form');
        $form->setStatus('PUBLISHED');

        $parameterBag = $this->createMock(ParameterBagInterface::class);
        $parameterBag->method('get')->with('frontend_url')->willReturn('http://localhost:3000');

        $logger = $this->createMock(LoggerInterface::class);

        $formEmbedService = new FormEmbedService(
            $parameterBag,
            $logger
        );

        $result = $formEmbedService->generateEmbedCode($form);

        $this->assertEquals($form->getId(), $result->formId);
        $this->assertStringContainsString('<iframe', $result->embedCode);
        $this->assertStringContainsString('height="600"', $result->embedCode);
        $this->assertStringContainsString('http://localhost:3000/embed/', $result->embedUrl);
        $this->assertStringContainsString($form->getId(), $result->embedUrl);
    }

    /**
     * @throws Exception
     */
    public function testGenerateEmbedCodeWithCustomization(): void
    {
        $form = new Form();
        $form->setId(Uuid::v4());
        $form->setStatus('PUBLISHED');

        $customization = [
            'width' => '800px',
            'height' => '400px',
            'border' => '1px solid #ccc',
            'borderRadius' => '12px',
        ];

        $parameterBag = $this->createMock(ParameterBagInterface::class);
        $parameterBag->method('get')->with('frontend_url')->willReturn('http://localhost:3000');

        $logger = $this->createMock(LoggerInterface::class);

        $formEmbedService = new FormEmbedService(
            $parameterBag,
            $logger
        );

        $result = $formEmbedService->generateEmbedCode($form, $customization);

        $this->assertEquals($customization, $result->customization);
        $this->assertStringContainsString('width: 800px', $result->embedCode);
        $this->assertStringContainsString('height="400"', $result->embedCode);
        $this->assertStringContainsString('border: 1px solid #ccc', $result->embedCode);
        $this->assertStringContainsString('border-radius: 12px', $result->embedCode);
    }


    /**
     * @throws Exception
     */
    public function testGenerateEmbedCodeFormNotPublished(): void
    {
        $form = new Form();
        $form->setId(Uuid::v4());
        $form->setStatus('DRAFT'); // Pas publié

        $parameterBag = $this->createMock(ParameterBagInterface::class);
        $logger = $this->createMock(LoggerInterface::class);

        $formEmbedService = new FormEmbedService(
            $parameterBag,
            $logger
        );

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le formulaire doit être publié pour générer un code d\'intégration');

        $formEmbedService->generateEmbedCode($form);
    }

    /**
     * @throws Exception
     */
    public function testGenerateEmbedCodeWithValidCustomization(): void
    {
        $form = new Form();
        $form->setId(Uuid::v4());
        $form->setStatus('PUBLISHED');

        $customization = [
            'width' => '100%',
            'height' => '500px',
            'border' => 'none',
            'borderRadius' => '8px',
            'boxShadow' => '0px 4px 6px rgba(0, 0, 0, 0.1)',
        ];

        $parameterBag = $this->createMock(ParameterBagInterface::class);
        $parameterBag->method('get')->with('frontend_url')->willReturn('http://localhost:3000');

        $logger = $this->createMock(LoggerInterface::class);

        $formEmbedService = new FormEmbedService(
            $parameterBag,
            $logger
        );

        $result = $formEmbedService->generateEmbedCode($form, $customization);

        $this->assertEquals($customization, $result->customization);
        $this->assertStringContainsString('width: 100%', $result->embedCode);
        $this->assertStringContainsString('height="500"', $result->embedCode);
        $this->assertStringContainsString('border: none', $result->embedCode);
        $this->assertStringContainsString('border-radius: 8px', $result->embedCode);
        $this->assertStringContainsString('box-shadow: 0px 4px 6px rgba(0, 0, 0, 0.1)', $result->embedCode);
    }

    /**
     * @throws Exception
     */
    public function testGenerateEmbedCodeWithInvalidCustomization(): void
    {
        $form = new Form();
        $form->setId(Uuid::v4());
        $form->setStatus('PUBLISHED');

        $invalidCustomization = [
            'width' => '100%',
            'height' => '500px',
            'border' => 'none',
            'borderRadius' => '8px',
            'boxShadow' => '0px 4px 6px rgba(0, 0, 0, 0.1)',
            'invalidProperty' => 'should be filtered out',
            'script' => '<script>alert("xss")</script>',
        ];

        $parameterBag = $this->createMock(ParameterBagInterface::class);
        $parameterBag->method('get')->with('frontend_url')->willReturn('http://localhost:3000');

        $logger = $this->createMock(LoggerInterface::class);

        $formEmbedService = new FormEmbedService(
            $parameterBag,
            $logger
        );

        $result = $formEmbedService->generateEmbedCode($form, $invalidCustomization);

        // Les propriétés valides doivent être préservées
        $this->assertStringContainsString('width: 100%', $result->embedCode);
        $this->assertStringContainsString('height="500"', $result->embedCode);
        $this->assertStringContainsString('border: none', $result->embedCode);
        $this->assertStringContainsString('border-radius: 8px', $result->embedCode);
        $this->assertStringContainsString('box-shadow: 0px 4px 6px rgba(0, 0, 0, 0.1)', $result->embedCode);

        // Les propriétés invalides doivent être filtrées
        $this->assertStringNotContainsString('invalidProperty', $result->embedCode);
        $this->assertStringNotContainsString('<script>', $result->embedCode);
        $this->assertStringNotContainsString('alert("xss")', $result->embedCode);
    }

    /**
     * @throws Exception
     */
    public function testGenerateEmbedCodeWithXssAttempt(): void
    {
        $form = new Form();
        $form->setId(Uuid::v4());
        $form->setStatus('PUBLISHED');

        $xssCustomization = [
            'width' => '100%',
            'height' => '500px',
            'border' => 'none',
            'borderRadius' => '8px',
            'boxShadow' => '0px 4px 6px rgba(0, 0, 0, 0.1)',
            'onload' => 'alert("xss")',
            'onclick' => 'alert("xss")',
            'style' => 'background: url(javascript:alert("xss"))',
        ];

        $parameterBag = $this->createMock(ParameterBagInterface::class);
        $parameterBag->method('get')->with('frontend_url')->willReturn('http://localhost:3000');

        $logger = $this->createMock(LoggerInterface::class);

        $formEmbedService = new FormEmbedService(
            $parameterBag,
            $logger
        );

        $result = $formEmbedService->generateEmbedCode($form, $xssCustomization);

        // Les propriétés valides doivent être préservées
        $this->assertStringContainsString('width: 100%', $result->embedCode);
        $this->assertStringContainsString('height="500"', $result->embedCode);
        $this->assertStringContainsString('border: none', $result->embedCode);
        $this->assertStringContainsString('border-radius: 8px', $result->embedCode);
        $this->assertStringContainsString('box-shadow: 0px 4px 6px rgba(0, 0, 0, 0.1)', $result->embedCode);

        // Les tentatives XSS doivent être filtrées
        $this->assertStringNotContainsString('onload', $result->embedCode);
        $this->assertStringNotContainsString('onclick', $result->embedCode);
        $this->assertStringNotContainsString('javascript:', $result->embedCode);
        $this->assertStringNotContainsString('alert("xss")', $result->embedCode);
    }

    /**
     * @throws Exception
     */
    public function testGenerateEmbedCodeWithEmptyCustomization(): void
    {
        $form = new Form();
        $form->setId(Uuid::v4());
        $form->setStatus('PUBLISHED');

        $parameterBag = $this->createMock(ParameterBagInterface::class);
        $parameterBag->method('get')->with('frontend_url')->willReturn('http://localhost:3000');

        $logger = $this->createMock(LoggerInterface::class);

        $formEmbedService = new FormEmbedService(
            $parameterBag,
            $logger
        );

        $result = $formEmbedService->generateEmbedCode($form, []);

        $this->assertEquals([], $result->customization);
        $this->assertStringContainsString('<iframe', $result->embedCode);
        $this->assertStringContainsString('height="600"', $result->embedCode);
    }

    /**
     * @throws Exception
     */
    public function testGenerateEmbedCodeWithNullCustomization(): void
    {
        $form = new Form();
        $form->setId(Uuid::v4());
        $form->setStatus('PUBLISHED');

        $parameterBag = $this->createMock(ParameterBagInterface::class);
        $parameterBag->method('get')->with('frontend_url')->willReturn('http://localhost:3000');

        $logger = $this->createMock(LoggerInterface::class);

        $formEmbedService = new FormEmbedService(
            $parameterBag,
            $logger
        );

        $result = $formEmbedService->generateEmbedCode($form);

        $this->assertEquals([], $result->customization);
        $this->assertStringContainsString('<iframe', $result->embedCode);
        $this->assertStringContainsString('height="600"', $result->embedCode);
    }
}
