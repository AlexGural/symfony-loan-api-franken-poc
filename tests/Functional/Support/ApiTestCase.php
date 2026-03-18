<?php

declare(strict_types=1);

namespace App\Tests\Functional\Support;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

abstract class ApiTestCase extends WebTestCase
{
    protected KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->recreateSchema();
    }

    private function recreateSchema(): void
    {
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $metadata = $em->getMetadataFactory()->getAllMetadata();

        $schemaTool = new SchemaTool($em);
        // Doctrine's dropSchema() is best-effort and may leave tables behind
        // (e.g. due to FK/DDL order). For functional tests we want a fully clean DB.
        $schemaTool->dropDatabase();
        $schemaTool->createSchema($metadata);
    }

    protected function jsonRequest(string $method, string $uri, ?array $data = null): ?array
    {
        $this->client->request(
            $method,
            $uri,
            server: ['CONTENT_TYPE' => 'application/json'],
            content: null !== $data ? json_encode($data, JSON_THROW_ON_ERROR) : null,
        );

        $content = $this->client->getResponse()->getContent();

        if ('' === $content || false === $content) {
            return null;
        }

        $decoded = json_decode($content, true);

        return is_array($decoded) ? $decoded : null;
    }

    protected function getStatusCode(): int
    {
        return $this->client->getResponse()->getStatusCode();
    }
}
