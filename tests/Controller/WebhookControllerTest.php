<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

class WebhookControllerTest extends WebTestCase
{
    public function testMissingTokenIsRejected(): void
    {
        $client = static::createClient();
        $client->request('POST', '/', ['payload' => '{"event":"library.new"}']);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    public function testWrongTokenIsRejected(): void
    {
        $client = static::createClient();
        $client->request('POST', '/?token=wrong', ['payload' => '{"event":"library.new"}']);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    public function testValidTokenButMissingPayloadIsBadRequest(): void
    {
        $client = static::createClient();
        $client->request('POST', '/?token=test-secret-token');

        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
    }

    public function testValidTokenButInvalidPayloadIsBadRequest(): void
    {
        $client = static::createClient();
        $client->request('POST', '/?token=test-secret-token', ['payload' => 'not-json']);

        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
    }

    public function testGetMethodIsNotAllowed(): void
    {
        $client = static::createClient();
        $client->request('GET', '/?token=test-secret-token');

        $this->assertResponseStatusCodeSame(Response::HTTP_METHOD_NOT_ALLOWED);
    }
}
