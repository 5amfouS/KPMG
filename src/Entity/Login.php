<?php

namespace App\Entity;

use App\Repository\LoginRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: LoginRepository::class)]
class Login
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $adresse_ip = null;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    private ?\DateTime $date_login = null;

    #[ORM\Column(length: 255)]
    private ?string $succes = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getAdresseIp(): ?string
    {
        return $this->adresse_ip;
    }

    public function setAdresseIp(string $adresse_ip): static
    {
        $this->adresse_ip = $adresse_ip;

        return $this;
    }

    public function getDateLogin(): ?\DateTime
    {
        return $this->date_login;
    }

    public function setDateLogin(\DateTime $date_login): static
    {
        $this->date_login = $date_login;

        return $this;
    }

    public function getSucces(): ?string
    {
        return $this->succes;
    }

    public function setSucces(string $succes): static
    {
        $this->succes = $succes;

        return $this;
    }
}
