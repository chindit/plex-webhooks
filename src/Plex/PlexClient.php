<?php

namespace App\Plex;

use Chindit\PlexApi\Account;
use Chindit\PlexApi\Exceptions\InvalidAccountException;
use Chindit\PlexApi\Exceptions\UnreachableServerException;
use Chindit\PlexApi\Model\Movie;
use Chindit\PlexApi\PlexServer;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;

class PlexClient
{
    private ?PlexServer $server = null;
    private bool $connectionAttempted = false;

    public function __construct(
        private readonly string $host,
        private readonly string $token,
    ) {
    }

    public function getFromKey(int|string $ratingKey): ?Movie
    {
        try {
            if (!$this->connectionAttempted) {
                $this->connectionAttempted = true;
                if ($this->host === '' || $this->token === '') {
                    return null;
                }

                $url = str_contains($this->host, '://') ? $this->host : 'http://' . $this->host;
                // Keep the previous default Plex port when the URL has no explicit port.
                if (parse_url($url, PHP_URL_PORT) === null) {
                    $url = preg_replace('~^(https?://[^/?#]+)~', '$1:32400', $url);
                }
                $this->server = (new Account($this->token, $url))->getServer();
            }

            return $this->server?->getFromKey((string) $ratingKey);
        } catch (InvalidAccountException|UnreachableServerException|ExceptionInterface) {
            // Other digest entries can still use their stored webhook metadata.
            return null;
        }
    }
}
