<?php

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * @ORM\Entity(repositoryClass=UserRepository::class)
 */
class User implements UserInterface
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="string", length=180, unique=true)
     */
    private $firebaseUid;

    /**
     * @ORM\Column(type="json", nullable=true)
     */
    private $user;

    /**
     * @ORM\Column(type="json", nullable=true)
     */
    private $data;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    private $loginAt;

    /**
     * @ORM\Column(type="json")
     */
    private $roles = [];

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getFirebaseUid(): ?string
    {
        return $this->firebaseUid;
    }

    public function setFirebaseUid(string $firebaseUid): self
    {
        $this->firebaseUid = $firebaseUid;

        return $this;
    }

    /**
     * A visual identifier that represents this user.
     *
     * @see UserInterface
     */
    public function getUsername(): string
    {
        return (string) $this->firebaseUid;
    }

    /**
     * @see UserInterface
     */
    public function getRoles(): array
    {
        $roles = $this->roles;
        // guarantee every user at least has ROLE_USER
        $roles[] = 'ROLE_USER';

        return array_unique($roles);
    }

    public function setRoles(array $roles): self
    {
        $this->roles = $roles;

        return $this;
    }

    /**
     * @see UserInterface
     */
    public function getPassword()
    {
        // not needed for apps that do not check user passwords
    }

    /**
     * @see UserInterface
     */
    public function getSalt()
    {
        // not needed for apps that do not check user passwords
    }

    /**
     * @see UserInterface
     */
    public function eraseCredentials()
    {
        // If you store any temporary, sensitive data on the user, clear it here
        // $this->plainPassword = null;
    }

    public function getUser(): Object
    {
        return json_decode($this->user);
    }

    public function setUser(string $user): self
    {
        $this->user = $user;

        return $this;
    }

    public function getData(): ?Object
    {
        $decoded = json_decode($this->data);
        if(!$decoded) return null;
        return json_decode($this->data);
    }

    public function setData(string $data): self
    {
        $this->data = $data;

        return $this;
    }

    public function getLoginAt(): ?\DateTimeInterface
    {
        return $this->loginAt;
    }

    public function setLoginAt(\DateTimeInterface $loginAt): self
    {
        $this->loginAt = $loginAt;

        return $this;
    }
}
