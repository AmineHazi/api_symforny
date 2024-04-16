<?php

namespace App\Entity;

use App\Repository\AnalyseResultRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AnalyseResultRepository::class)]
class AnalyseResult
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 512)]
    private ?string $url = null;

    #[ORM\Column]
    private ?int $links_nbr = null;

    #[ORM\Column(type: Types::ARRAY, nullable: true)]
    private ?array $links_found = null;

    #[ORM\Column]
    private ?int $images_nbr = null;

    #[ORM\Column(type: Types::TIME_MUTABLE)]
    private ?\DateTimeInterface $total_time = null;

    #[ORM\Column(nullable: true)]
    private ?bool $analyse_en_cours = null;

    #[ORM\Column(type: Types::ARRAY, nullable: true)]
    private ?array $links_to_analyse = [];

    #[ORM\Column(type: Types::INTEGER)]
    private ?int $depth = 0;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    private ?int $dockerNb = 0;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUrl(): ?string
    {
        return $this->url;
    }

    public function getLinksToAnalyse(): ?array
    {
        return $this->links_to_analyse;
    }

    public function setLinksToAnalyse(array $links_to_analyse): self
    {
        $this->links_to_analyse = $links_to_analyse;
        return $this;
    }

    public function setUrl(string $url): static
    {
        $this->url = $url;

        return $this;
    }

    public function getLinksNbr(): ?int
    {
        return $this->links_nbr;
    }

    public function setLinksNbr(int $links_nbr): static
    {
        $this->links_nbr = $links_nbr;

        return $this;
    }

    public function getLinksFound(): ?array
    {
        return $this->links_found;
    }

    public function setLinksFound(?array $links_found): static
    {
        $this->links_found = $links_found;

        return $this;
    }

    public function getImagesNbr(): ?int
    {
        return $this->images_nbr;
    }

    public function setImagesNbr(int $images_nbr): static
    {
        $this->images_nbr = $images_nbr;

        return $this;
    }

    public function getTotalTime(): ?\DateTimeInterface
    {
        return $this->total_time;
    }

    public function setTotalTime(\DateTimeInterface $total_time): static
    {
        $this->total_time = $total_time;

        return $this;
    }

    public function isAnalyseEnCours(): ?bool
    {
        return $this->analyse_en_cours;
    }

    public function setAnalyseEnCours(?bool $analyse_en_cours): static
    {
        $this->analyse_en_cours = $analyse_en_cours;

        return $this;
    }

    public function getDepth(): ?int
    {
        return $this->depth;
    }

    public function setDepth(int $depth): self
    {
        $this->depth = $depth;
        return $this;
    }

    public function getDockerNb(): ?int
    {
        return $this->dockerNb;
    }

    public function setDockerNb(int $dockerNb): self
    {
        $this->dockerNb = $dockerNb;
        return $this;
    }
}
