<?php

namespace App\Mailer;

use Symfony\Component\Mailer\Exception\UnsupportedSchemeException;
use Symfony\Component\Mailer\Transport\AbstractTransportFactory;
use Symfony\Component\Mailer\Transport\Dsn;
use Symfony\Component\Mailer\Transport\TransportInterface;

class GmailApiTransportFactory extends AbstractTransportFactory
{
    public function create(Dsn $dsn): TransportInterface
    {
        if ('gmail+api' === $dsn->getScheme()) {
            $clientId = $dsn->getUser() ?: $this->getEnv('GOOGLE_CLIENT_ID');
            $clientSecret = $dsn->getPassword() ?: $this->getEnv('GOOGLE_CLIENT_SECRET');
            $refreshToken = $dsn->getOption('refresh_token') ?: $this->getEnv('GOOGLE_REFRESH_TOKEN');

            if (!$clientId || !$clientSecret || !$refreshToken) {
                throw new \RuntimeException(sprintf(
                    'Missing Gmail API credentials. Set GOOGLE_CLIENT_ID, GOOGLE_CLIENT_SECRET, and GOOGLE_REFRESH_TOKEN env vars, or use DSN: %s',
                    'gmail+api://CLIENT_ID:CLIENT_SECRET@default?refresh_token=REFRESH_TOKEN'
                ));
            }

            return new GmailApiTransport(
                $clientId,
                $clientSecret,
                $refreshToken,
                $this->client,
                $this->dispatcher,
            $this->logger,
            );
        }

        throw new UnsupportedSchemeException($dsn, 'gmail+api');
    }

    private function getEnv(string $name): ?string
    {
        return $_ENV[$name] ?? $_SERVER[$name] ?? getenv($name) ?: null;
    }

    protected function getSupportedSchemes(): array
    {
        return ['gmail+api'];
    }
}
