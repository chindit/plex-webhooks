<?php

namespace App\Entity;

use App\Repository\PlexWebhookRepository;
use Chindit\PlexApi\Model\Movie;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PlexWebhookRepository::class)]
class PlexWebhook
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::JSON)]
    private array $content = [];

    #[ORM\Column(length: 255)]
    private ?string $type = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::BLOB, nullable: true)]
    private $thumb = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getContent(): array
    {
        return $this->content;
    }

    public function setContent(array $content): self
    {
        $this->content = $content;

        return $this;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(string $type): self
    {
        $this->type = $type;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getThumb()
    {
        return $this->thumb;
    }

    public function setThumb($thumb): self
    {
        $this->thumb = $thumb;

        return $this;
    }

    public function asMovie(): Movie
    {
        $metadata = $this->content['Metadata'];

        $values = array_merge($metadata, [
            'ratingKey' => (string)$metadata['ratingKey'],
            'year' => (string)($metadata['year'] ?? ''),
            'duration' => (string)($metadata['duration'] ?? ''),
            'rating' => (string)($metadata['rating'] ?? 0),
            'aspectRatio' => (float)($metadata['aspectRatio'] ?? 0),
            'bitrate' => (int)($metadata['bitrate'] ?? 0),
            'width' => (int)($metadata['width'] ?? 0),
            'height' => (int)($metadata['height'] ?? 0),
            'genres' => array_column($metadata['Genre'] ?? [], 'tag'),
            'directors' => array_column($metadata['Director'] ?? [], 'tag'),
            'writers' => array_column($metadata['Writer'] ?? [], 'tag'),
            'actors' => array_column($metadata['Role'] ?? [], 'tag'),
            'countries' => array_column($metadata['Country'] ?? [], 'tag'),
        ]);

        return new Movie($values);
    }
}
