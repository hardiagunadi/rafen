<?php

namespace App\Services;

use App\Models\OltConnection;
use Illuminate\Support\Facades\Process;
use RuntimeException;

class HsgqSnmpCollector
{
    private const OID_SYS_DESCR = '1.3.6.1.2.1.1.1.0';

    private const OID_SYS_OBJECT_ID = '1.3.6.1.2.1.1.2.0';

    /**
     * @return array<int, string>
     */
    public static function availableModels(): array
    {
        return array_keys((array) config('olt.hsgq_models', []));
    }

    /**
     * @param  array<string, mixed>  $connectionConfig
     * @return array{
     *   matched_model: string|null,
     *   sys_descr: string|null,
     *   sys_object_id: string|null,
     *   candidate_models: array<int, string>
     * }
     */
    public function detectModelFromSnmp(array $connectionConfig): array
    {
        $tempConnection = $this->buildConnectionFromConfig($connectionConfig);

        $sysDescr = $this->readScalarValue($tempConnection, self::OID_SYS_DESCR);
        $sysObjectId = $this->readScalarValue($tempConnection, self::OID_SYS_OBJECT_ID);

        if ($sysDescr === null && $sysObjectId === null) {
            throw new RuntimeException('Gagal membaca identitas perangkat lewat SNMP. Pastikan host, port, dan community benar.');
        }

        $matchedModel = $this->matchModelFromDeviceMetadata($sysDescr, $sysObjectId);

        return [
            'matched_model' => $matchedModel,
            'sys_descr' => $sysDescr,
            'sys_object_id' => $sysObjectId,
            'candidate_models' => $this->suggestSimilarModels($sysDescr, $sysObjectId),
        ];
    }

    /**
     * @param  array<string, mixed>  $connectionConfig
     * @return array{
     *   model: string,
     *   oids: array<string, string>,
     *   probe: array<string, array{oid: string, sample_count: int, detected: bool}>,
     *   detected_fields: int
     * }
     */
    public function detectMappingFromModel(array $connectionConfig): array
    {
        $requestedModel = trim((string) ($connectionConfig['olt_model'] ?? ''));
        if ($requestedModel === '') {
            throw new RuntimeException('Model OLT belum dipilih.');
        }

        $profile = $this->resolveModelProfile($requestedModel, $connectionConfig);
        if (! is_array($profile['profile'])) {
            $suggestions = implode(', ', $profile['candidate_models']);
            throw new RuntimeException(
                'Profil OID untuk model "'.$requestedModel.'" tidak ditemukan. '.
                ($suggestions !== '' ? 'Kandidat terdekat: '.$suggestions.'. ' : '').
                'Isi OID manual jika model belum tersedia.'
            );
        }

        $resolvedModel = $profile['model'];
        $oids = (array) ($profile['profile']['oids'] ?? []);
        if (empty($oids)) {
            throw new RuntimeException('Profil OID model ini belum dikonfigurasi.');
        }

        $tempConnection = $this->buildConnectionFromConfig($connectionConfig);
        $probe = [];

        foreach ($oids as $field => $oid) {
            $oidValue = trim((string) $oid);
            if ($oidValue === '') {
                continue;
            }

            $sampleCount = count($this->walkByIndex($tempConnection, $oidValue));
            $probe[(string) $field] = [
                'oid' => $oidValue,
                'sample_count' => $sampleCount,
                'detected' => $sampleCount > 0,
            ];
        }

        $detectedFields = collect($probe)
            ->where('detected', true)
            ->count();

        if ($detectedFields === 0) {
            throw new RuntimeException($this->buildOidDetectionFailureMessage($connectionConfig, $resolvedModel));
        }

        return [
            'model' => $resolvedModel,
            'oids' => $oids,
            'probe' => $probe,
            'detected_fields' => $detectedFields,
        ];
    }

    /**
     * @param  array<string, mixed>  $connectionConfig
     */
    private function buildOidDetectionFailureMessage(array $connectionConfig, string $resolvedModel): string
    {
        try {
            $detectedModelData = $this->detectModelFromSnmp($connectionConfig);
        } catch (RuntimeException) {
            return 'Tidak ada OID model yang terbaca. Pastikan host/SNMP/community dan model OLT benar.';
        }

        $detectedModel = $detectedModelData['matched_model'] ?? null;
        $sysDescr = $detectedModelData['sys_descr'] ?? null;

        if (is_string($detectedModel) && $detectedModel !== '' && $detectedModel !== $resolvedModel) {
            return 'SNMP terhubung, tetapi profil OID untuk model "'.$resolvedModel
                .'" tidak cocok dengan perangkat ini. Perangkat terdeteksi sebagai "'.$detectedModel.'".';
        }

        if (is_string($sysDescr) && $sysDescr !== '') {
            return 'SNMP terhubung ke perangkat "'.$sysDescr
                .'", tetapi profil OID untuk model "'.$resolvedModel
                .'" belum cocok. Isi OID manual atau kirim hasil snmpwalk untuk pemetaan.';
        }

        return 'SNMP terhubung, tetapi profil OID untuk model "'.$resolvedModel
            .'" belum cocok. Isi OID manual atau kirim hasil snmpwalk untuk pemetaan.';
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function collect(OltConnection $oltConnection): array
    {
        $oidMap = [
            'serial_number' => $oltConnection->oid_serial,
            'onu_name' => $oltConnection->oid_onu_name,
            'distance_raw' => $oltConnection->oid_distance,
            'rx_onu_raw' => $oltConnection->oid_rx_onu,
            'tx_onu_raw' => $oltConnection->oid_tx_onu,
            'rx_olt_raw' => $oltConnection->oid_rx_olt,
            'tx_olt_raw' => $oltConnection->oid_tx_olt,
            'status' => $oltConnection->oid_status,
        ];

        $configuredOidMap = array_filter($oidMap, fn (?string $oid) => filled($oid));
        if (empty($configuredOidMap)) {
            throw new RuntimeException('OID SNMP belum dikonfigurasi. Isi minimal satu OID pada data OLT.');
        }

        $walkResults = [];
        foreach ($configuredOidMap as $field => $baseOid) {
            $walkResults[$field] = $this->walkByIndex($oltConnection, (string) $baseOid);
        }

        $preferredIndexes = collect([
            'serial_number',
            'onu_name',
            'status',
            'distance_raw',
            'rx_onu_raw',
            'tx_onu_raw',
        ])->flatMap(fn (string $field) => array_keys($walkResults[$field] ?? []))
            ->unique()
            ->values()
            ->all();

        $allIndexes = ! empty($preferredIndexes)
            ? $preferredIndexes
            : collect($walkResults)
                ->flatMap(fn (array $valuesByIndex) => array_keys($valuesByIndex))
                ->unique()
                ->values()
                ->all();

        if (empty($allIndexes)) {
            return [];
        }

        $rows = [];
        foreach ($allIndexes as $onuIndex) {
            $ponAndOnu = $this->inferPonAndOnu($onuIndex);
            $rawPayload = [
                'rx_onu' => $this->resolveWalkValue($walkResults['rx_onu_raw'] ?? [], $onuIndex),
                'tx_onu' => $this->resolveWalkValue($walkResults['tx_onu_raw'] ?? [], $onuIndex),
                'rx_olt' => $this->resolveWalkValue($walkResults['rx_olt_raw'] ?? [], $onuIndex, true),
                'tx_olt' => $this->resolveWalkValue($walkResults['tx_olt_raw'] ?? [], $onuIndex, true),
                'distance' => $this->resolveWalkValue($walkResults['distance_raw'] ?? [], $onuIndex),
                'status' => $this->resolveWalkValue($walkResults['status'] ?? [], $onuIndex),
            ];

            $rows[] = [
                'onu_index' => $onuIndex,
                'pon_interface' => $ponAndOnu['pon_interface'],
                'onu_number' => $ponAndOnu['onu_number'],
                'serial_number' => $walkResults['serial_number'][$onuIndex] ?? null,
                'onu_name' => $walkResults['onu_name'][$onuIndex] ?? null,
                'distance_m' => $this->parseDistanceValue($rawPayload['distance']),
                'rx_onu_dbm' => $this->parseOpticalValue($rawPayload['rx_onu'], 'rx_onu'),
                'tx_onu_dbm' => $this->parseOpticalValue($rawPayload['tx_onu'], 'tx_onu'),
                'rx_olt_dbm' => $this->parseOpticalValue($rawPayload['rx_olt'], 'rx_olt'),
                'tx_olt_dbm' => $this->parseOpticalValue($rawPayload['tx_olt'], 'tx_olt'),
                'status' => $this->normalizeOnuStatus($rawPayload['status']),
                'raw_payload' => $rawPayload,
            ];
        }

        return $rows;
    }

    /**
     * @param  array<string, mixed>  $connectionConfig
     * @return array{
     *   model: string,
     *   profile: array<string, mixed>|null,
     *   candidate_models: array<int, string>
     * }
     */
    private function resolveModelProfile(string $requestedModel, array $connectionConfig): array
    {
        $modelProfiles = (array) config('olt.hsgq_models', []);
        $exactProfile = $modelProfiles[$requestedModel] ?? null;
        if (is_array($exactProfile)) {
            return [
                'model' => $requestedModel,
                'profile' => $exactProfile,
                'candidate_models' => [],
            ];
        }

        $detectedModelData = $this->detectModelFromSnmp($connectionConfig);
        $matchedModel = $detectedModelData['matched_model'];
        if (is_string($matchedModel) && isset($modelProfiles[$matchedModel]) && is_array($modelProfiles[$matchedModel])) {
            return [
                'model' => $matchedModel,
                'profile' => $modelProfiles[$matchedModel],
                'candidate_models' => $detectedModelData['candidate_models'],
            ];
        }

        return [
            'model' => $requestedModel,
            'profile' => null,
            'candidate_models' => $detectedModelData['candidate_models'],
        ];
    }

    private function matchModelFromDeviceMetadata(?string $sysDescr, ?string $sysObjectId): ?string
    {
        $models = self::availableModels();
        $bestMatch = null;
        $bestScore = 0;

        foreach ($models as $model) {
            $score = $this->scoreModelCandidate($model, $sysDescr, $sysObjectId);
            if ($score > $bestScore) {
                $bestScore = $score;
                $bestMatch = $model;
            }
        }

        if ($bestScore < 3) {
            return null;
        }

        return $bestMatch;
    }

    /**
     * @return array<int, string>
     */
    private function suggestSimilarModels(?string $sysDescr, ?string $sysObjectId): array
    {
        $scores = [];
        foreach (self::availableModels() as $model) {
            $score = $this->scoreModelCandidate($model, $sysDescr, $sysObjectId);
            if ($score > 0) {
                $scores[$model] = $score;
            }
        }

        arsort($scores);

        return array_slice(array_keys($scores), 0, 3);
    }

    private function scoreModelCandidate(string $model, ?string $sysDescr, ?string $sysObjectId): int
    {
        $normalizedReferences = array_filter([
            $this->normalizeModelToken($sysDescr),
            $this->normalizeModelToken($sysObjectId),
        ], fn (string $value): bool => $value !== '');

        if (empty($normalizedReferences)) {
            return 0;
        }

        $tokens = $this->extractModelTokens($model);
        if (empty($tokens)) {
            return 0;
        }

        $score = 0;
        foreach ($tokens as $token) {
            foreach ($normalizedReferences as $reference) {
                if (str_contains($reference, $token)) {
                    $score += strlen($token);
                    break;
                }
            }
        }

        return $score;
    }

    /**
     * @return array<int, string>
     */
    private function extractModelTokens(string $value): array
    {
        preg_match_all('/[A-Z0-9]{3,}/', strtoupper($value), $matches);
        $tokens = array_unique($matches[0] ?? []);

        return array_values(array_filter($tokens, fn (string $token): bool => $token !== 'HSGQ'));
    }

    private function normalizeModelToken(?string $value): string
    {
        if ($value === null) {
            return '';
        }

        return preg_replace('/[^A-Z0-9]/', '', strtoupper($value)) ?? '';
    }

    private function readScalarValue(OltConnection $oltConnection, string $oid): ?string
    {
        $normalizedOid = ltrim(trim($oid), '.');

        $command = sprintf(
            'snmpwalk -On -v2c -c %s -t %d -r %d %s %s',
            escapeshellarg($oltConnection->snmp_community),
            $oltConnection->snmp_timeout,
            $oltConnection->snmp_retries,
            escapeshellarg($oltConnection->host.':'.$oltConnection->snmp_port),
            escapeshellarg('.'.$normalizedOid),
        );

        $result = Process::timeout(max(8, $oltConnection->snmp_timeout + 3))
            ->run($command);

        if ($result->failed()) {
            $error = trim($result->errorOutput() ?: $result->output());
            $normalizedError = strtolower($error);
            if (str_contains($normalizedError, 'timeout') || str_contains($normalizedError, 'no response')) {
                return null;
            }

            throw new RuntimeException('SNMP read gagal: '.$error);
        }

        return $this->parseScalarOutput($result->output(), $normalizedOid);
    }

    private function parseScalarOutput(string $output, string $oid): ?string
    {
        $lines = preg_split('/\r\n|\r|\n/', trim($output)) ?: [];

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            if (! preg_match('/^\.?(?<oid>[0-9\.]+)\s*=\s*(?<type>[^:]+):\s*(?<value>.*)$/', $line, $matches)) {
                continue;
            }

            if (ltrim((string) $matches['oid'], '.') !== $oid) {
                continue;
            }

            return $this->normalizeText($matches['value']);
        }

        return null;
    }

    /**
     * @return array<string, string>
     */
    private function walkByIndex(OltConnection $oltConnection, string $baseOid): array
    {
        $normalizedBaseOid = ltrim(trim($baseOid), '.');

        $command = sprintf(
            'snmpwalk -On -v2c -c %s -t %d -r %d %s %s',
            escapeshellarg($oltConnection->snmp_community),
            $oltConnection->snmp_timeout,
            $oltConnection->snmp_retries,
            escapeshellarg($oltConnection->host.':'.$oltConnection->snmp_port),
            escapeshellarg('.'.$normalizedBaseOid),
        );

        $result = Process::timeout(max(8, $oltConnection->snmp_timeout + 3))
            ->run($command);

        if ($result->failed()) {
            throw new RuntimeException('SNMP walk gagal: '.trim($result->errorOutput() ?: $result->output()));
        }

        return $this->parseWalkOutput($result->output(), $normalizedBaseOid);
    }

    /**
     * @return array<string, string>
     */
    private function parseWalkOutput(string $output, string $baseOid): array
    {
        $valuesByIndex = [];
        $lines = preg_split('/\r\n|\r|\n/', trim($output)) ?: [];

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            if (! preg_match('/^\.?(?<oid>[0-9\.]+)\s*=\s*(?<type>[^:]+):\s*(?<value>.*)$/', $line, $matches)) {
                continue;
            }

            $resolvedOid = ltrim((string) $matches['oid'], '.');
            $baseWithDot = $baseOid.'.';
            if (! str_starts_with($resolvedOid, $baseWithDot)) {
                continue;
            }

            $index = substr($resolvedOid, strlen($baseWithDot));
            if ($index === false || $index === '') {
                continue;
            }

            $normalizedIndex = $this->normalizeWalkIndex($baseOid, $index);
            if ($normalizedIndex === null || $normalizedIndex === '') {
                continue;
            }

            $valuesByIndex[$normalizedIndex] = $this->normalizeText($matches['value']);
        }

        return $valuesByIndex;
    }

    /**
     * @return array{pon_interface: string|null, onu_number: string|null}
     */
    private function inferPonAndOnu(string $onuIndex): array
    {
        if (ctype_digit($onuIndex)) {
            $decodedIndex = $this->decodeHsgqOnuIndex((int) $onuIndex);
            if ($decodedIndex !== null) {
                return [
                    'pon_interface' => 'PON'.$decodedIndex['pon'],
                    'onu_number' => (string) $decodedIndex['onu'],
                ];
            }
        }

        $segments = array_values(array_filter(explode('.', $onuIndex), fn (string $segment) => $segment !== ''));
        $segmentCount = count($segments);

        if ($segmentCount >= 2) {
            return [
                'pon_interface' => $segments[$segmentCount - 2],
                'onu_number' => $segments[$segmentCount - 1],
            ];
        }

        return [
            'pon_interface' => null,
            'onu_number' => $segments[0] ?? null,
        ];
    }

    /**
     * @return array{pon: int, onu: int}|null
     */
    private function decodeHsgqOnuIndex(int $onuIndex): ?array
    {
        $relativeIndex = $onuIndex - 16777216;
        if ($relativeIndex <= 0) {
            return null;
        }

        $pon = intdiv($relativeIndex, 256);
        $onu = $relativeIndex % 256;

        if ($pon < 1 || $onu < 1) {
            return null;
        }

        return [
            'pon' => $pon,
            'onu' => $onu,
        ];
    }

    private function parseOpticalValue(?string $value, ?string $field = null): ?float
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        $normalized = str_replace(',', '.', $value);
        if (preg_match('/-?\d+(?:\.\d+)?/', $normalized, $matches) !== 1) {
            return null;
        }

        $numericValue = (float) $matches[0];
        if ((int) $numericValue === -2147483648) {
            return null;
        }

        return round($this->normalizeOpticalScale($numericValue, $field), 2);
    }

    private function normalizeOpticalScale(float $numericValue, ?string $field): float
    {
        return match ($field) {
            'tx_olt' => abs($numericValue) >= 1000 ? $numericValue / 1000 : $numericValue,
            'rx_onu', 'tx_onu', 'rx_olt' => abs($numericValue) >= 100 ? $numericValue / 100 : $numericValue,
            default => abs($numericValue) >= 1000
                ? $numericValue / 1000
                : (abs($numericValue) >= 100 ? $numericValue / 100 : $numericValue),
        };
    }

    private function parseDistanceValue(?string $value): ?int
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        if (preg_match('/\d+/', $value, $matches) !== 1) {
            return null;
        }

        return (int) $matches[0];
    }

    private function normalizeText(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim($value);
        if ($trimmed === '') {
            return null;
        }

        if (str_starts_with($trimmed, '"') && str_ends_with($trimmed, '"')) {
            $trimmed = trim($trimmed, '"');
        }

        return trim($trimmed) === '' ? null : trim($trimmed);
    }

    private function normalizeOnuStatus(?string $value): ?string
    {
        $normalized = $this->normalizeText($value);
        if ($normalized === null) {
            return null;
        }

        return match (strtolower($normalized)) {
            '1', 'up', 'online', 'true' => 'online',
            '2', 'down', 'offline', 'false' => 'offline',
            default => $normalized,
        };
    }

    private function normalizeWalkIndex(string $baseOid, string $index): ?string
    {
        if (! str_starts_with($baseOid, '1.3.6.1.4.1.50224.3.3.3.1.')) {
            return $index;
        }

        if (str_ends_with($index, '.65535.65535')) {
            return null;
        }

        if (str_ends_with($index, '.0.0')) {
            $segments = explode('.', $index);

            return $segments[0] !== '' ? $segments[0] : null;
        }

        return $index;
    }

    /**
     * @param  array<string, string>  $valuesByIndex
     */
    private function resolveWalkValue(array $valuesByIndex, string $onuIndex, bool $allowPonFallback = false): ?string
    {
        if (array_key_exists($onuIndex, $valuesByIndex)) {
            return $valuesByIndex[$onuIndex];
        }

        if (! $allowPonFallback) {
            return null;
        }

        $ponIndex = $this->derivePonIndex($onuIndex);

        return $ponIndex !== null ? ($valuesByIndex[$ponIndex] ?? null) : null;
    }

    private function derivePonIndex(string $onuIndex): ?string
    {
        if (! ctype_digit($onuIndex)) {
            return null;
        }

        return (string) (intdiv((int) $onuIndex, 256) * 256);
    }

    /**
     * @param  array<string, mixed>  $connectionConfig
     */
    private function buildConnectionFromConfig(array $connectionConfig): OltConnection
    {
        $oltConnection = new OltConnection;
        $oltConnection->host = (string) ($connectionConfig['host'] ?? '');
        $oltConnection->snmp_port = (int) ($connectionConfig['snmp_port'] ?? 161);
        $oltConnection->snmp_version = (string) ($connectionConfig['snmp_version'] ?? '2c');
        $oltConnection->snmp_community = (string) ($connectionConfig['snmp_community'] ?? '');
        $oltConnection->snmp_timeout = (int) ($connectionConfig['snmp_timeout'] ?? 5);
        $oltConnection->snmp_retries = (int) ($connectionConfig['snmp_retries'] ?? 1);

        return $oltConnection;
    }
}
