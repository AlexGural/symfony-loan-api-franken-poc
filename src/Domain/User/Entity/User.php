<?php

declare(strict_types=1);

namespace App\Domain\User\Entity;

use App\Domain\User\ValueObject\Email;
use App\Domain\User\ValueObject\UserId;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'users')]
class User
{
    #[ORM\Id]
    #[ORM\Column(type: 'guid')]
    private string $id;

    #[ORM\Column(length: 255)]
    private string $name;

    #[ORM\Column(length: 255, unique: true)]
    private string $email;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct(UserId $id, string $name, Email $email)
    {
        $this->id = $id->value;
        $this->name = $name;
        $this->email = $email->value;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): UserId
    {
        return new UserId($this->id);
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getEmail(): Email
    {
        return new Email($this->email);
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function changeEmail(Email $newEmail): void
    {
        $this->email = $newEmail->value;
    }
}
