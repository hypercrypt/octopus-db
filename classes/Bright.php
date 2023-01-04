<?php

require_once __DIR__ . '/../vendor/autoload.php';

use GuzzleHttp\Client;
use Tightenco\Collect;

readonly class Bright {

    public string $token;
    private Client $client;

    public function __construct(
        string $username,
        string $password,
        public PDO|null $database_connection = null,
        public string $application_id = 'b0f1b774-a586-4f72-9edd-27ead8aa7a8d',
    )
    {
        $this->authenticate(
            username: $username,
            password: $password
        );

        $this->client = new Client([
            'base_uri' => 'https://api.glowmarkt.com/api/v0-1/',
            'headers' => [
                'Content-Type' => 'application/json',
                'applicationId' => $this->application_id,
                'token' => $this->token,
            ],
        ]);
    }

    public function authenticate(string $username, string $password): bool
    {
        $token_statement = db()->prepare("
            SELECT `token` FROM `__tokens__` WHERE `service`='bright' AND `username` = :username LIMIT 1
        ");
        $token_statement->execute(['username' => $username]);
        $token = $token_statement->fetchColumn();

        if (str_starts_with(haystack: $token ?? '', needle: 'ey'))
        {
            $token_data = json_decode(base64_decode(explode('.', $token)[1]));
            if ($token_data->exp > time() + 300)
            {
                $this->token = $token;
                return true;
            }
        }

        $client = new Client();

        $data = json_decode($client->post(
            uri: 'https://api.glowmarkt.com/api/v0-1/auth',
            options: [
                'json' => [
                    "username" => $username,
                    "password" => $password,
                    "applicationId" => $this->application_id
                ],
                'headers' => [
                    'Content-Type' => 'application/json',
                    'applicationId' => $this->application_id,
                ]
            ]
        )->getBody());

        $this->token = $data->token ?? '';
        $this->database_connection?->prepare("
            REPLACE INTO `__tokens__` SET
                `service` = 'bright',
                `username` = :username,
                `token` = :token,
                `other_info` = :other_info
        ")
            ->execute([
                'username' => $username,
                'token' => $this->token,
                'other_info' => base64_decode(explode('.', $this->token)[1]),
            ]);

        return $data->valid ?? false;

    }

    public function find_virtualentity(): array
    {
        return collect(json_decode($this->client->get(uri: 'virtualentity')->getBody()))
            ->map(fn($ve) => $this->fetch_json('virtualentity/' . $ve->veId))
            ->flatMap(fn($ve) => $ve->resources)
            ->map(fn($r) => [$r->resourceId, $r->name])
            ->all();
    }

    public function fetch_json(string $path, array|object|null $query=null): stdClass
    {
        return json_decode(
            $this->client->get(
                uri: $path,
                options: [
                    'query' => $query
                ]
            )->getBody()
        );
    }
    public function current_usage(string $resourceId): int|null
    {
        return $this->fetch_json(
            path: "resource/$resourceId/current",
        )?->data[0][1];
    }

    function past_usage(string $resourceId, string $from, string $to, string $interval='PT30M')
    {
        return $this->fetch_json(
            path: "resource/$resourceId/readings",
            query: [
                'period' => $interval,
                'function' => 'sum',
                'from' => $from,
                'to' => $to,
            ]
        )->data ?? [];
    }
}
