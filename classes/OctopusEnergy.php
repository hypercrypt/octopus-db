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
    public function fetch_json(string $uri): stdClass
    {
        return json_decode(
            json: $this->client
                ->get($uri)
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
        $id = $this->product_id_from_product_code($code);
        $fuel = $this->fuel_from_tariff_code($code);

        if (str_starts_with(
            haystack: $code,
            needle: 'E-2R'
        ))
        {
            return [
                'day'   => $this->fetch_json("products/$id/$fuel-tariffs/$code/day-unit-rates/?page=$page&page_size=100"),
                'night' => $this->fetch_json("products/$id/$fuel-tariffs/$code/night-unit-rates/?page=$page&page_size=100")
            ];
        }
        else
        {
            return [
                'standard' => $this->fetch_json("products/$id/$fuel-tariffs/$code/standard-unit-rates/?page=$page&page_size=100")
            ];
        }
    }


    public function get_usage($meter, $page, $fuel, $mpoint): ?stdClass
    {
        return $this->fetch_json("$fuel-meter-points/$mpoint/meters/$meter/consumption/?page=$page&page_size=200");
    }
}

$config = require  __DIR__ . '/../config.php';

$oe = new OctopusEnergy(
    api_key: $config['octopus']['apiKey'],
    account_number: $config['octopus']['accountNumber'],
);
