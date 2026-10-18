<?php

namespace App\Tests\Plex;

use App\Plex\PlexClient;
use Chindit\PlexApi\Model\Movie;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

class PlexClientTest extends TestCase
{
    private ?Process $server = null;
    private string $requestLog;

    protected function setUp(): void
    {
        $this->requestLog = tempnam(sys_get_temp_dir(), 'plex-client-test-');
    }

    protected function tearDown(): void
    {
        $this->server?->stop();
        unlink($this->requestLog);
    }

    public function testConnectsThroughAccountOnlyWhenNeededAndReusesTheServer(): void
    {
        $url = $this->startServer();
        $client = new PlexClient($url, 'test-token');
        self::assertSame('', file_get_contents($this->requestLog));

        $movie = $client->getFromKey('10');
        self::assertInstanceOf(Movie::class, $movie);
        self::assertSame('10', $movie->getRatingKey());
        self::assertSame('Plex movie', $movie->getTitle());
        self::assertSame('Plex summary', $movie->getSummary());
        self::assertSame(3900, $movie->getDuration());
        self::assertSame(['Drama'], $movie->getGenres());
        self::assertSame(['An Actor'], $movie->getActors());
        self::assertSame('11', $client->getFromKey(11)?->getRatingKey());
        self::assertSame([
            '/identity',
            '/identity',
            '/library/metadata/10',
            '/library/metadata/11',
        ], file($this->requestLog, FILE_IGNORE_NEW_LINES));
    }

    public function testPreservesTheDefaultPlexPortForUrlsWithoutAPort(): void
    {
        $this->startServer(32400);
        $client = new PlexClient('http://127.0.0.1', 'test-token');

        self::assertSame('10', $client->getFromKey('10')?->getRatingKey());
    }

    public function testAcceptsAHostWithoutAScheme(): void
    {
        $url = $this->startServer();
        $client = new PlexClient(substr($url, strlen('http://')), 'test-token');

        self::assertSame('10', $client->getFromKey('10')?->getRatingKey());
    }

    public function testUnavailableOrMissingMetadataDoesNotAbortTheDigest(): void
    {
        $client = new PlexClient($this->startServer(), 'test-token');

        self::assertNull($client->getFromKey('404'));
        self::assertNull($client->getFromKey('500'));
        self::assertInstanceOf(Movie::class, $client->getFromKey('10'));
    }

    public function testFailedAuthenticationIsNotRetriedForEveryItem(): void
    {
        $client = new PlexClient($this->startServer(), 'wrong-token');

        self::assertNull($client->getFromKey('10'));
        self::assertNull($client->getFromKey('11'));
        self::assertSame(['/identity'], file($this->requestLog, FILE_IGNORE_NEW_LINES));
    }

    public function testMissingConfigurationDoesNotAttemptAConnection(): void
    {
        $url = $this->startServer();

        self::assertNull((new PlexClient($url, ''))->getFromKey('10'));
        self::assertNull((new PlexClient('', 'test-token'))->getFromKey('10'));
        self::assertSame('', file_get_contents($this->requestLog));
    }

    private function startServer(int $port = 0): string
    {
        $socket = @stream_socket_server('tcp://127.0.0.1:' . $port, $errorCode, $errorMessage);
        if ($socket === false && $port === 32400 && in_array($errorCode, [48, 98], true)) {
            self::markTestSkipped('Port 32400 is already in use.');
        }
        self::assertIsResource($socket, $errorMessage);
        $address = stream_socket_get_name($socket, false);
        fclose($socket);
        $this->server = new Process([
            PHP_BINARY,
            '-S',
            $address,
            dirname(__DIR__) . '/Fixtures/plex-router.php',
        ], env: ['PLEX_TEST_REQUEST_LOG' => $this->requestLog, 'XDEBUG_MODE' => 'off']);
        $this->server->setTimeout(5);
        $this->server->start();
        self::assertTrue($this->server->waitUntil(
            static fn (string $type, string $output) => str_contains($output, 'Development Server') && str_contains($output, 'started')
        ), $this->server->getErrorOutput());

        return 'http://' . $address;
    }
}
