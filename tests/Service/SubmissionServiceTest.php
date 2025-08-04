<?php

namespace App\Tests\Service;

use App\Dto\SubmitFormDto;
use App\Entity\Form;
use App\Entity\FormVersion;
use App\Entity\FormField;
use App\Entity\QuotaStatus;
use App\Entity\Submission;
use App\Entity\User;
use App\Repository\FormRepository;
use App\Repository\FormVersionRepository;
use App\Repository\QuotaStatusRepository;
use App\Service\SubmissionService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class SubmissionServiceTest extends TestCase
{
    private SubmissionService $service;

    private $em;
    private $validator;
    private $formRepository;
    private $formVersionRepository;
    private $quotaStatusRepository;
    private $mailer;
    private $params;

    protected function setUp(): void
    {
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->validator = $this->createMock(ValidatorInterface::class);
        $this->formRepository = $this->createMock(FormRepository::class);
        $this->formVersionRepository = $this->createMock(FormVersionRepository::class);
        $this->quotaStatusRepository = $this->createMock(QuotaStatusRepository::class);
        $this->mailer = $this->createMock(MailerInterface::class);
        $this->params = $this->createMock(ParameterBagInterface::class);

        $this->params->method('get')->with('env(FRONTEND_URL)')->willReturn('http://localhost:3000/');

        $this->service = new SubmissionService(
            $this->em,
            $this->validator,
            $this->formRepository,
            $this->formVersionRepository,
            $this->quotaStatusRepository,
            $this->mailer,
            $this->params
        );
    }

    public function testSubmitSuccess(): void
    {
        $formId = 'form-1';
        $ip = '127.0.0.1';
        $dto = new SubmitFormDto(['email' => 'test@example.com']);

        $user = $this->createMock(User::class);
        $user->method('getId')->willReturn('user-1');
        $user->method('getEmail')->willReturn('proprietaire@example.com');

        $form = $this->createMock(Form::class);
        $form->method('getUser')->willReturn($user);
        $form->method('getId')->willReturn($formId);
        $form->method('getTitle')->willReturn('Titre du formulaire');

        $formField = new FormField();
        $formField->setId('email')->setLabel('Email')->setType('email')->setIsRequired(true);

        $formVersion = new FormVersion();
        $formVersion->setSchema(['fields' => [['name' => 'email', 'type' => 'email', 'required' => true]]]);
        $formVersion->addFormField($formField);

        $this->formRepository->method('find')->willReturn($form);
        $this->formVersionRepository->method('findOneBy')->willReturn($formVersion);
        $this->quotaStatusRepository->method('findOneBy')->willReturn(null);

        $this->em->expects($this->once())->method('persist');
        $this->em->expects($this->once())->method('flush');
        $this->mailer->expects($this->once())->method('send');

        $submission = $this->service->submit($formId, $dto, $ip);

        $this->assertInstanceOf(Submission::class, $submission);
        $this->assertEquals($ip, $submission->getIpAddress());
    }

    public function testSubmitFailsWhenFormNotFound(): void
    {
        $this->formRepository->method('find')->willReturn(null);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Formulaire introuvable.");

        $dto = new SubmitFormDto(['email' => 'test@example.com']);
        $this->service->submit('invalid-form-id', $dto, '127.0.0.1');
    }

    public function testSubmitFailsWhenNoFormVersion(): void
    {
        $user = new User();
        $form = new Form();
        $form->setUser($user);

        $this->formRepository->method('find')->willReturn($form);
        $this->formVersionRepository->method('findOneBy')->willReturn(null);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("Aucune version de formulaire trouvée.");

        $dto = new SubmitFormDto(['email' => 'test@example.com']);
        $this->service->submit('form-1', $dto, '127.0.0.1');
    }

    public function testSubmitFailsWithInvalidEmail(): void
    {
        $user = new User();
        $form = new Form();
        $form->setUser($user)->setTitle('Formulaire');

        $formField = new FormField();
        $formField->setId('email')->setLabel('Email')->setType('email')->setIsRequired(true);

        $formVersion = new FormVersion();
        $formVersion->setSchema(['fields' => [['name' => 'email', 'type' => 'email', 'required' => true]]]);
        $formVersion->addFormField($formField);

        $this->formRepository->method('find')->willReturn($form);
        $this->formVersionRepository->method('findOneBy')->willReturn($formVersion);

        $dto = new SubmitFormDto(['email' => 'not-an-email']);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Le champ 'Email' doit être une adresse email valide.");

        $this->service->submit('form-1', $dto, '127.0.0.1');
    }

    public function testSubmitFailsWhenQuotaExceeded(): void
    {
        $user = new User();
        $form = new Form();
        $form->setUser($user)->setTitle('Formulaire');

        $formField = new FormField();
        $formField->setId('email')->setLabel('Email')->setType('email')->setIsRequired(true);

        $formVersion = new FormVersion();
        $formVersion->setSchema(['fields' => [['name' => 'email', 'type' => 'email', 'required' => true]]]);
        $formVersion->addFormField($formField);

        $quota = new QuotaStatus();
        $quota->setUser($user)->setMonth(new \DateTimeImmutable('first day of this month'))->setSubmissionCount(1000);

        $this->formRepository->method('find')->willReturn($form);
        $this->formVersionRepository->method('findOneBy')->willReturn($formVersion);
        $this->quotaStatusRepository->method('findOneBy')->willReturn($quota);

        $dto = new SubmitFormDto(['email' => 'test@example.com']);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("Quota de soumissions atteint pour ce mois.");

        $this->service->submit('form-1', $dto, '127.0.0.1');
    }

    public function testSubmitFailsWithRegexValidation(): void
    {
        $user = new User();
        $form = new Form();
        $form->setUser($user);

        $formField = new FormField();
        $formField->setId('username')->setLabel('Nom d\'utilisateur')->setType('text')->setIsRequired(true);
        $formField->setValidationRules(['regex' => '^[a-zA-Z]+$', 'regexMessage' => 'Le nom d\'utilisateur est invalide.']);

        $formVersion = new FormVersion();
        $formVersion->setSchema(['fields' => [['name' => 'username', 'type' => 'text', 'required' => true]]]);
        $formVersion->addFormField($formField);

        $this->formRepository->method('find')->willReturn($form);
        $this->formVersionRepository->method('findOneBy')->willReturn($formVersion);

        $dto = new SubmitFormDto(['username' => 'John123']);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Le nom d'utilisateur est invalide.");

        $this->service->submit('form-1', $dto, '127.0.0.1');
    }

    public function testSubmitFailsWithMinLength(): void
    {
        $user = new User();
        $form = new Form();
        $form->setUser($user);

        $formField = new FormField();
        $formField->setId('bio')->setLabel('Biographie')->setType('textarea')->setIsRequired(true);
        $formField->setValidationRules(['minLength' => 10]);

        $formVersion = new FormVersion();
        $formVersion->setSchema(['fields' => [['name' => 'bio', 'type' => 'textarea', 'required' => true]]]);
        $formVersion->addFormField($formField);

        $this->formRepository->method('find')->willReturn($form);
        $this->formVersionRepository->method('findOneBy')->willReturn($formVersion);

        $dto = new SubmitFormDto(['bio' => 'Short']);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Le champ 'Biographie' doit contenir au moins 10 caractères.");

        $this->service->submit('form-1', $dto, '127.0.0.1');
    }

    public function testSubmitFailsWithMaxLength(): void
    {
        $user = new User();
        $form = new Form();
        $form->setUser($user);

        $formField = new FormField();
        $formField->setId('comment')->setLabel('Commentaire')->setType('textarea')->setIsRequired(true);
        $formField->setValidationRules(['maxLength' => 10]);

        $formVersion = new FormVersion();
        $formVersion->setSchema(['fields' => [['name' => 'comment', 'type' => 'textarea', 'required' => true]]]);
        $formVersion->addFormField($formField);

        $this->formRepository->method('find')->willReturn($form);
        $this->formVersionRepository->method('findOneBy')->willReturn($formVersion);

        $dto = new SubmitFormDto(['comment' => 'This is a very long comment.']);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Le champ 'Commentaire' ne peut pas dépasser 10 caractères.");

        $this->service->submit('form-1', $dto, '127.0.0.1');
    }

    public function testSubmitFailsWithMinNumber(): void
    {
        $user = new User();
        $form = new Form();
        $form->setUser($user);

        $formField = new FormField();
        $formField->setId('age')->setLabel('Âge')->setType('number')->setIsRequired(true);
        $formField->setValidationRules(['min' => 18]);

        $formVersion = new FormVersion();
        $formVersion->setSchema(['fields' => [['name' => 'age', 'type' => 'number', 'required' => true]]]);
        $formVersion->addFormField($formField);

        $this->formRepository->method('find')->willReturn($form);
        $this->formVersionRepository->method('findOneBy')->willReturn($formVersion);

        $dto = new SubmitFormDto(['age' => 16]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Le champ 'Âge' doit être supérieur ou égal à 18.");

        $this->service->submit('form-1', $dto, '127.0.0.1');
    }
}
