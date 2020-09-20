<?php

namespace App\Entity;
use Symfony\Component\Validator\Constraints as Assert;

class Card
{

    public function __toString()
    {
        return (string) $this->id;
    }
    /**
     * @var string
     * @Assert\NotBlank()
     * @Assert\Length(min = 2,max = 255)
     */
    private $email;
    /**
     * @var string
     * @Assert\NotBlank()
     * @Assert\Length(min = 2,max = 255)
     */
    private $name_sei;
    /**
     * @var string
     * @Assert\NotBlank()
     * @Assert\Length(min = 2,max = 255)
     */
    private $name_mei;
    /**
     * @var string
     * @Assert\NotBlank()
     * @Assert\Length(min = 7,max = 255)
     */
    private $zip;
    /**
     * @var string
     * @Assert\NotBlank()
     * @Assert\Length(min = 2,max = 255)
     */
    private $pref;
    /**
     * @var string
     * @Assert\NotBlank()
     * @Assert\Length(min = 2,max = 255)
     */
    private $addr1;
    /**
     * @var string
     * @Assert\NotBlank()
     * @Assert\Length(min = 2,max = 255)
     */
    private $addr2;
    /**
     * @var string
     */
    private $addr3;
    /**
     * @var string
     * @Assert\NotBlank()
     * @Assert\Length(min = 10,max = 15)
     */
    private $tel;


    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): self
    {
        $this->email = $email;

        return $this;
    }

    public function getNameSei(): ?string
    {
        return $this->name_sei;
    }

    public function setNameSei(string $name_sei): self
    {
        $this->name_sei = $name_sei;

        return $this;
    }

    public function getNameMei(): ?string
    {
        return $this->name_mei;
    }

    public function setNameMei(string $name_mei): self
    {
        $this->name_mei = $name_mei;

        return $this;
    }

    public function getName(): string
    {
        return $this->getNameSei().' '.$this->getNameMei();
    }

    public function getNameDestination(): ?string
    {
        return $this->name_destination;
    }

    public function setNameDestination(string $name_destination): self
    {
        $this->name_destination = $name_destination;

        return $this;
    }

    public function getZip(): ?string
    {
        return $this->zip;
    }

    public function setZip(string $zip): self
    {
        $this->zip = $zip;

        return $this;
    }

    public function getPref(): ?string
    {
        return $this->pref;
    }

    public function setPref(string $pref): self
    {
        $this->pref = $pref;

        return $this;
    }

    public function getAddr(): ?string
    {
        $addrs = array(
            $this->getPref(),
            $this->getAddr1(),
            $this->getAddr2(),
            $this->getAddr3()
        );
        return join($addrs, ' ');
    }

    public function getAddr1(): ?string
    {
        return $this->addr1;
    }

    public function setAddr1(string $addr1): self
    {
        $this->addr1 = $addr1;

        return $this;
    }

    public function getAddr2(): ?string
    {
        return $this->addr2;
    }

    public function setAddr2(string $addr2): self
    {
        $this->addr2 = $addr2;

        return $this;
    }

    public function getAddr3(): ?string
    {
        return $this->addr3;
    }

    public function setAddr3(?string $addr3): self
    {
        $this->addr3 = $addr3;

        return $this;
    }

    public function getTel(): ?string
    {
        return $this->tel;
    }

    public function setTel(string $tel): self
    {
        $this->tel = $tel;

        return $this;
    }

}
