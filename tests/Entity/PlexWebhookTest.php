<?php

namespace App\Tests\Entity;

use App\Entity\PlexWebhook;
use PHPUnit\Framework\TestCase;

class PlexWebhookTest extends TestCase
{
    public function testGetMetadataReturnsEmptyArrayWhenAbsent(): void
    {
        $webhook = (new PlexWebhook())->setContent(['event' => 'device.new']);

        $this->assertSame([], $webhook->getMetadata());
        $this->assertNull($webhook->getGuid());
    }

    public function testGetGuidReturnsMetadataGuid(): void
    {
        $webhook = (new PlexWebhook())->setContent([
            'event' => 'library.new',
            'Metadata' => ['guid' => 'plex://movie/abc'],
        ]);

        $this->assertSame('plex://movie/abc', $webhook->getGuid());
    }

    public function testAsMovieMapsAndCastsMetadata(): void
    {
        $webhook = (new PlexWebhook())->setContent([
            'event' => 'library.new',
            'Metadata' => [
                'ratingKey' => 12345,
                'title' => 'Blade Runner',
                'year' => 1982,
                'rating' => 8.1,
                'width' => 1920,
                'height' => 1080,
                'Genre' => [['tag' => 'Sci-Fi'], ['tag' => 'Thriller']],
                'Director' => [['tag' => 'Ridley Scott']],
                'Role' => [['tag' => 'Harrison Ford']],
            ],
        ]);

        $movie = $webhook->asMovie();

        $this->assertSame('12345', $movie->getRatingKey());
        $this->assertSame('Blade Runner', $movie->getTitle());
        $this->assertSame('1982', $movie->getYear());
        $this->assertSame(1920, $movie->getWidth());
        $this->assertSame(['Sci-Fi', 'Thriller'], $movie->getGenres());
        $this->assertSame(['Ridley Scott'], $movie->getDirectors());
        $this->assertSame(['Harrison Ford'], $movie->getActors());
    }

    public function testAsMovieToleratesMissingOptionalFields(): void
    {
        $webhook = (new PlexWebhook())->setContent([
            'event' => 'library.new',
            'Metadata' => ['title' => 'Untitled'],
        ]);

        $movie = $webhook->asMovie();

        $this->assertSame('', $movie->getRatingKey());
        $this->assertSame('', $movie->getYear());
        $this->assertSame(0, $movie->getWidth());
        $this->assertSame([], $movie->getGenres());
    }
}
