<?php

require_once __DIR__ . '/../vendor/autoload.php';

use GuzzleHttp\Client;

class OctopusEnergy {

    public readonly array $electricity_agreements;
    public readonly array $gas_agreements;

    public readonly array $meters;

    public function __construct(
        public readonly string $api_key,
        public readonly string $account_number,
    )
    {
        $this->client = new Client([
            'base_uri' => 'https://api.octopus.energy/v1/',
            'auth' => [$this->api_key, ''],
        ]);

        $account = $this->fetch_json('accounts/' . $this->account_number);
        $property = $account->properties[0];
        $this->electricity_agreements = $property->electricity_meter_points[0]->agreements ?? [];
        $this->gas_agreements         = $property->gas_meter_points[0]->agreements ?? [];

        $meters = [];
        foreach ($property->electricity_meter_points[0]->meters as $meter)
        {
            $meters[$meter->serial_number] = ['electricity', $property->electricity_meter_points[0]->mpan];
        }

        if (count($property->gas_meter_points) > 0)
        {
            foreach ($property->gas_meter_points[0]->meters as $meter)
            {
                $meters[$meter->serial_number] = ['gas', $property->gas_meter_points[0]->mprn];
            }
        }

        $this->meters = $meters;
    }

    private readonly Client $client;

    private ?string $graphql_token;
    public function graphql_auth(): bool {
        $token = $this->graphql_query(
            query: 'mutation ObtainKrakenToken($input: ObtainJSONWebTokenInput!) {obtainKrakenToken(input: $input) {token refreshToken}}',
            variables: [
                'input' => [
                    'APIKey' => $this->api_key
                ],
            ],
            auth: false
        )->data->obtainKrakenToken->token;

        if (str_starts_with($token, 'ey')) {
            $this->graphql_token = $token;
            return true;
        } else {
            return false;
        }
    }
    public function graphql_query(string $query, array $variables=[], bool $auth=true): stdClass
    {
        $headers = [
            'Content-Type' => 'application/json'
        ];

        if ($auth)
        {
            $headers['Authorization'] = 'JWT ' . $this->graphql_token;
        }
        return json_decode(
            $this->client->post(
                uri: 'graphql/',
                options: [
                    'headers' => $headers,
                    'json' => [
                        'query' => $query,
                        'variables' => $variables,
                    ]
                ]
            )->getBody()
        );
    }

    public function fetch_json(string $uri, array $query=[]): stdClass
    {
        return json_decode(
            json: $this->client
                ->get(
                    uri: $uri,
                    options: [
                        'query' => $query,
                    ],
                )
                ->getBody()
        );
    }

    private function product_id_from_product_code(string $code): string
    {
        return substr($code, 5, -2);
    }

    private function fuel_from_tariff_code(string $code): ?string
    {
        return match (substr($code, 0, 1)) {
            'E' => 'electricity',
            'G' => 'gas',
            default => null,
        };
    }

    public function get_prices($code, $page=1): array
    {
        $id   = $this->product_id_from_product_code($code);
        $fuel = $this->fuel_from_tariff_code($code);

        if (str_starts_with(
            haystack: $code,
            needle: 'E-2R'
        ))
        {
            return [
                'day'   => $this->fetch_json(
                    uri: "products/$id/$fuel-tariffs/$code/day-unit-rates/",
                    query: [
                        'page' => $page,
                        'page_size' => 100
                    ],
                ),
                'night' => $this->fetch_json(
                    uri: "products/$id/$fuel-tariffs/$code/night-unit-rates/",
                    query: [
                        'page' => $page,
                        'page_size' => 100
                    ],
                ),
            ];
        }
        else
        {
            return [
                'standard' => $this->fetch_json(
                    uri: "products/$id/$fuel-tariffs/$code/standard-unit-rates/",
                    query: [
                        'page' => $page,
                        'page_size' => 100
                    ],
                ),
            ];
        }
    }

    public function get_usage($meter, $page, $fuel, $mpoint): ?stdClass
    {
        return $this->fetch_json(
            uri: "$fuel-meter-points/$mpoint/meters/$meter/consumption/",
            query: [
                'page' => $page,
                'page_size' => 200
            ],
        );
    }

    static public function current_tariff(string $fuel): string
    {
        $f = strtoupper(substr(
            string: $fuel,
            offset: 0,
            length: 1,
        ));

        return db()->query("
            SELECT `tariff`
            FROM `tariff`
            WHERE `valid_to` >= UNIX_TIMESTAMP() 
                AND `valid_from` <= UNIX_TIMESTAMP()
                AND `tariff` LIKE '$f-%'
            LIMIT 1
        ")
        ->fetchColumn();
    }

    static public function select_price(int $timestamp, ?string $fuel): ?stdClass
    {
        $query = match ($fuel) {
            'electricity' => "
                SELECT
                    IFNULL(`r`.`value_exc_vat`, `p`.`value_exc_vat`)   AS `reduced_rate_exc_vat`,
                    IFNULL(`r`.`value_inc_vat`, `p`.`value_inc_vat`)   AS `reduced_rate_inc_vat`,
                    `p`.`value_exc_vat`                              AS `rate_exc_vat`,
                    `p`.`value_inc_vat`                              AS `rate_inc_vat`,
                    `r`.comment
                FROM `price` AS `p`
                    LEFT JOIN `reduced_rates` AS `r` 
                        ON r.valid_from <= $timestamp AND r.valid_to > $timestamp
                WHERE p.valid_from <= $timestamp AND p.valid_to > $timestamp
                  AND p.`tariff`=(
                      SELECT `tariff`
                      FROM `tariff`
                      WHERE `valid_to`   >  $timestamp 
                        AND `valid_from` <= $timestamp
                        AND `tariff` LIKE 'E-%'
                      LIMIT 1
                  )
                LIMIT 1",
            'gas' => "
               SELECT
                    `p`.`value_exc_vat`                              AS `reduced_rate_exc_vat`,
                    `p`.`value_inc_vat`                              AS `reduced_rate_inc_vat`,
                    `p`.`value_exc_vat`                              AS `rate_exc_vat`,
                    `p`.`value_inc_vat`                              AS `rate_inc_vat`,
                     ''                                              AS `comment`
                FROM `price` AS `p`
                WHERE p.valid_from <= $timestamp AND p.valid_to > $timestamp
                  AND p.`tariff`=(
                      SELECT `tariff`
                      FROM `tariff`
                      WHERE `valid_to`   >  $timestamp
                        AND `valid_from` <= $timestamp
                        AND `tariff` LIKE 'G-%'
                      LIMIT 1
                  )
                LIMIT 1",
            default => '',
        };

        $data = db()->query($query)->fetchObject();

        if ($data === false) return null;
        else return $data;
    }
}
