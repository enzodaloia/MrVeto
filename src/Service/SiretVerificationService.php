<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class SiretVerificationService
{
    private const EXPECTED_APE_CODES = ['75.00Z', '7500Z'];
    private const API_URL = 'https://recherche-entreprises.api.gouv.fr/search';

    public function __construct(private HttpClientInterface $httpClient)
    {
    }

    /**
     * @return array{
     *     status: string,
     *     message: string,
     *     siret: ?string,
     *     exists: ?bool,
     *     isActive: ?bool,
     *     apeCode: ?string,
     *     isVeterinaryActivity: ?bool,
     *     name: ?string,
     *     address: ?string
     * }
     */
    public function verify(?string $siret): array
    {
        $hasSiret = $siret !== null && trim($siret) !== '';
        $normalizedSiret = $this->normalizeSiret($siret);

        if ($normalizedSiret === null) {
            if ($hasSiret) {
                return $this->result(
                    'danger',
                    'Le SIRET renseigné doit contenir exactement 14 chiffres.',
                    null,
                    false,
                    null,
                    null,
                    null,
                    null,
                    null
                );
            }

            return $this->result(
                'warning',
                'Aucun SIRET renseigné pour effectuer la vérification.',
                null,
                null,
                null,
                null,
                null,
                null,
                null
            );
        }

        try {
            $response = $this->httpClient->request('GET', self::API_URL, [
                'query' => [
                    'q' => $normalizedSiret,
                    'per_page' => 1,
                ],
                'timeout' => 1.5,
                'max_duration' => 2.0,
            ]);

            if ($response->getStatusCode() >= 400) {
                return $this->unavailableResult($normalizedSiret);
            }

            $rawBody = $response->getContent(false);
            $payload = json_decode($rawBody, true, 512, JSON_THROW_ON_ERROR);
            if (!is_array($payload)) {
                return $this->unavailableResult($normalizedSiret);
            }
        } catch (TransportExceptionInterface|\JsonException) {
            return $this->unavailableResult($normalizedSiret);
        }

        $establishment = $this->extractFirstResult($payload);
        if ($establishment === null) {
            return $this->result(
                'danger',
                'SIRET introuvable',
                $normalizedSiret,
                false,
                null,
                null,
                null,
                null,
                null
            );
        }

        $apeCode = $this->normalizeApeCode($establishment['apeCode'] ?? null);
        $isActive = $this->isActiveEstablishment($establishment['administrativeStatus'] ?? null);
        $returnedSiret = $this->normalizeSiret($establishment['siret'] ?? null);
        $siretMatches = $returnedSiret === $normalizedSiret;
        $isVeterinaryActivity = $apeCode !== null && in_array($apeCode, self::EXPECTED_APE_CODES, true);
        $status = $siretMatches === true && $isActive === true && $isVeterinaryActivity === true ? 'success' : 'danger';
        $message = $status === 'success'
            ? 'Établissement actif. Activité vétérinaire confirmée.'
            : 'Vérification nécessaire par l’administrateur.';

        return $this->result(
            $status,
            $message,
            $returnedSiret ?? $normalizedSiret,
            $siretMatches,
            $isActive,
            $apeCode,
            $isVeterinaryActivity,
            $establishment['name'] ?? null,
            $establishment['address'] ?? null
        );
    }

    private function normalizeSiret(?string $siret): ?string
    {
        if ($siret === null) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $siret);
        if ($digits === null || strlen($digits) !== 14) {
            return null;
        }

        return $digits;
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, string|null>|null
     */
    private function extractFirstResult(array $payload): ?array
    {
        $results = $payload['results'] ?? [];
        if (!is_array($results) || $results === [] || !is_array($results[0])) {
            return null;
        }

        $result = $results[0];
        $siege = isset($result['siege']) && is_array($result['siege']) ? $result['siege'] : [];

        return [
            'siret' => $this->stringOrNull($result['siret'] ?? $siege['siret'] ?? null),
            'name' => $this->stringOrNull($result['nom_complet'] ?? $result['nom_raison_sociale'] ?? $result['denomination'] ?? null),
            'address' => $this->stringOrNull($result['adresse'] ?? $result['geo_adresse'] ?? $siege['adresse'] ?? $siege['geo_adresse'] ?? null),
            'apeCode' => $this->stringOrNull($result['activite_principale'] ?? $siege['activite_principale'] ?? null),
            'administrativeStatus' => $this->stringOrNull($result['etat_administratif'] ?? $siege['etat_administratif'] ?? null),
        ];
    }

    private function normalizeApeCode(?string $apeCode): ?string
    {
        if ($apeCode === null || trim($apeCode) === '') {
            return null;
        }

        return strtoupper(str_replace(' ', '', $apeCode));
    }

    private function isActiveEstablishment(?string $administrativeStatus): ?bool
    {
        if ($administrativeStatus === null || trim($administrativeStatus) === '') {
            return null;
        }

        return strtoupper($administrativeStatus) === 'A';
    }

    private function stringOrNull(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $value = trim($value);
        return $value === '' ? null : $value;
    }

    private function unavailableResult(string $siret): array
    {
        return $this->result(
            'warning',
            'Vérification nécessaire par l’administrateur.',
            $siret,
            null,
            null,
            null,
            null,
            null,
            null
        );
    }

    private function result(
        string $status,
        string $message,
        ?string $siret,
        ?bool $exists,
        ?bool $isActive,
        ?string $apeCode,
        ?bool $isVeterinaryActivity,
        ?string $name,
        ?string $address
    ): array {
        return [
            'status' => $status,
            'message' => $message,
            'siret' => $siret,
            'exists' => $exists,
            'isActive' => $isActive,
            'apeCode' => $apeCode,
            'isVeterinaryActivity' => $isVeterinaryActivity,
            'name' => $name,
            'address' => $address,
        ];
    }
}
