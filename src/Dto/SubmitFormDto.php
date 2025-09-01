<?php

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

class SubmitFormDto
{
    /**
     * @var array<string, mixed>
     */
    #[Assert\NotBlank(message: 'Les données du formulaire sont obligatoires')]
    #[Assert\Type(type: 'array', message: 'Les données doivent être un tableau')]
    private array $data = [];
    public function __construct(array $data = [])
    {
        $this->data = $data;
    }

    /**
     * @return array<string, mixed>
     */
    public function getData(): array
    {
        return $this->data;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function setData(array $data): void
    {
        $this->data = $data;
    }
}