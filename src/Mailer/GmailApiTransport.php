<?php

namespace App\Mailer;

use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\MessageConverter;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class GmailApiTransport extends AbstractTransport
{
    private HttpClientInterface $httpClient;
    private string $clientId;
    private string $clientSecret;
    private string $refreshToken;

    public function __construct(
        string $clientId,
        string $clientSecret,
        string $refreshToken,
        ?HttpClientInterface $client = null,
        ?EventDispatcherInterface $dispatcher = null,
        ?LoggerInterface $logger = null,
    ) {
        parent::__construct($dispatcher, $logger);
        $this->clientId = $clientId;
        $this->clientSecret = $clientSecret;
        $this->refreshToken = $refreshToken;
        $this->httpClient = $client ?? HttpClient::create();
    }

    public function __toString(): string
    {
        return 'gmail+api://';
    }

    protected function doSend(SentMessage $message): void
    {
        $email = $message->getMessage();
        if (!$email instanceof Email) {
            throw new \RuntimeException('Expected Email instance, got ' . get_class($email));
        }

        if (!$email->getTo()) {
            throw new \RuntimeException('Email has no To address');
        }

        $accessToken = $this->getAccessToken();

        $mimeMessage = MessageConverter::toMimeEntity($email);
        $rawMessage = $mimeMessage->toString();

        $encoded = rtrim(strtr(base64_encode($rawMessage), '+/', '-_'), '=');

        $response = $this->httpClient->request('POST', 'https://gmail.googleapis.com/gmail/v1/users/me/messages/send', [
            'headers' => [
                'Authorization' => 'Bearer ' . $accessToken,
                'Content-Type' => 'application/json',
            ],
            'json' => [
                'raw' => $encoded,
            ],
        ]);

        $status = $response->getStatusCode();

        if ($status < 200 || $status >= 300) {
            $body = $response->getContent(false);
            throw new \RuntimeException(sprintf(
                'Gmail API error (HTTP %d): %s',
                $status,
                $body
            ));
        }

        $message->setMessageId($email->getHeaders()->getHeaderBody('MessageId') ?? '');
    }

    private function getAccessToken(): string
    {
        $response = $this->httpClient->request('POST', 'https://oauth2.googleapis.com/token', [
            'body' => [
                'client_id' => $this->clientId,
                'client_secret' => $this->clientSecret,
                'refresh_token' => $this->refreshToken,
                'grant_type' => 'refresh_token',
            ],
        ]);

        $status = $response->getStatusCode();
        $data = $response->toArray(false);

        if ($status < 200 || $status >= 300 || !isset($data['access_token'])) {
            throw new \RuntimeException(sprintf(
                'Failed to get access token: HTTP %d, %s',
                $status,
                json_encode($data)
            ));
        }

        return $data['access_token'];
    }
}
