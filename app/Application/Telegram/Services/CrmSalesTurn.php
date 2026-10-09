<?php

declare(strict_types=1);

namespace App\Application\Telegram\Services;

use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Whether a penalty is already the sales manager's: the request is in one
 * of client_checks.sales_turn_statuses ("Актуальный") and the CRM has a
 * carrier price on it - the operator found the carrier, the client is now
 * waiting on sales.
 *
 * Any doubt answers null and the penalty goes where the bot says: the CRM
 * not configured or not answering, the request not found, no price, no
 * sales manager on it.
 */
final class CrmSalesTurn
{
    public function __construct(
        private readonly CrmApiClient $crm,
    ) {
    }

    /**
     * @return array{
     *     sales_name: string,
     *     sales_id: int|null,
     *     carrier_price: string,
     *     currency: string|null,
     * }|null
     */
    public function find(?string $requestNumber, ?string $crmStatus): ?array
    {
        $statuses = (array) config('client_checks.sales_turn_statuses', []);

        /*
         * The raw bytes too: an invisible character in the bot's status
         * reads the same in the log and still fails the comparison.
         */
        Log::info('Sales turn: start', [
            'request_number' => $requestNumber,
            'crm_status' => $crmStatus,
            'crm_status_hex' => $crmStatus !== null ? bin2hex($crmStatus) : null,
            'sales_turn_statuses' => $statuses,
        ]);

        if ($requestNumber === null) {
            Log::info('Sales turn: no request number, the penalty goes as the bot says');

            return null;
        }

        if (! $this->statusMatches($crmStatus)) {
            Log::info('Sales turn: status does not match, the penalty goes as the bot says', [
                'request_number' => $requestNumber,
                'crm_status' => $crmStatus,
                'sales_turn_statuses' => $statuses,
            ]);

            return null;
        }

        Log::info('Sales turn: status matches, asking the CRM', ['request_number' => $requestNumber]);

        if (! $this->crm->configured()) {
            Log::warning(
                'Sales turn: CRM API is not configured (CRM_API_EMAIL, CRM_API_PASSWORD), the penalty goes as the bot says',
                ['request_number' => $requestNumber],
            );

            return null;
        }

        try {
            $body = $this->crm->searchQueries($requestNumber);
        } catch (Throwable $e) {
            Log::warning(
                'Sales turn: CRM lookup failed, the penalty goes as the bot says',
                [
                    'request_number' => $requestNumber,
                    'error' => $e->getMessage(),
                ],
            );

            return null;
        }

        $found = (array) ($body['data']['data'] ?? []);

        Log::info('Sales turn: CRM answered', [
            'request_number' => $requestNumber,
            'success' => $body['success'] ?? null,
            'total' => $body['data']['total'] ?? null,
            'custom_ids' => array_map(
                static fn (mixed $query): mixed => is_array($query) ? ($query['custom_id'] ?? null) : null,
                $found,
            ),
            'top_level_keys' => array_keys($body),
        ]);

        $query = $this->query($body, $requestNumber);

        if ($query === null) {
            Log::info('Sales turn: CRM has no request with this number, the penalty goes as the bot says', ['request_number' => $requestNumber]);

            return null;
        }

        Log::info('Sales turn: request found', [
            'request_number' => $requestNumber,
            'query_id' => $query['id'] ?? null,
            'query_status_id' => $query['query_status_id'] ?? null,
            'cancelled_at' => $query['cancelled_at'] ?? null,
            'sales' => isset($query['sales']) && is_array($query['sales'])
                ? ['id' => $query['sales']['id'] ?? null, 'name' => $query['sales']['name'] ?? null]
                : null,
            'payments' => array_map(
                static fn (mixed $payment): mixed => is_array($payment)
                    ? [
                        'type' => $payment['payment_type'] ?? null,
                        'price' => $payment['price'] ?? null,
                        'currency' => $payment['currency']['name'] ?? null,
                    ]
                    : null,
                (array) ($query['payments'] ?? []),
            ),
        ]);

        $carrier = $this->carrierPayment($query);
        $salesName = trim((string) ($query['sales']['name'] ?? ''));

        if ($carrier === null || $salesName === '') {
            Log::info(
                'Sales turn: no carrier price or sales manager in the CRM, the penalty goes as the bot says',
                ['request_number' => $requestNumber, 'carrier_price' => $carrier !== null, 'sales' => $salesName !== ''],
            );

            return null;
        }

        $turn = [
            'sales_name' => $salesName,
            'sales_id' => isset($query['sales']['id']) ? (int) $query['sales']['id'] : null,
            'carrier_price' => (string) $carrier['price'],
            'currency' => isset($carrier['currency']['name']) ? (string) $carrier['currency']['name'] : null,
        ];

        Log::info('Sales turn: the penalty is the sales manager\'s', ['request_number' => $requestNumber, ...$turn]);

        return $turn;
    }

    private function statusMatches(?string $crmStatus): bool
    {
        if ($crmStatus === null) {
            return false;
        }

        $status = mb_strtolower(trim($crmStatus));

        foreach ((array) config('client_checks.sales_turn_statuses', []) as $candidate) {
            if (mb_strtolower(trim((string) $candidate)) === $status) {
                return true;
            }
        }

        return false;
    }

    /**
     * The search is a search: only the request with exactly this number.
     *
     * @param array<string, mixed> $body
     * @return array<string, mixed>|null
     */
    private function query(array $body, string $requestNumber): ?array
    {
        foreach ((array) ($body['data']['data'] ?? []) as $query) {
            if (is_array($query) && strcasecmp((string) ($query['custom_id'] ?? ''), $requestNumber) === 0) {
                return $query;
            }
        }

        return null;
    }

    /**
     * The "carrier" payment, when it has a price above zero.
     *
     * @param array<string, mixed> $query
     * @return array<string, mixed>|null
     */
    private function carrierPayment(array $query): ?array
    {
        foreach ((array) ($query['payments'] ?? []) as $payment) {
            if (is_array($payment)
                && ($payment['payment_type'] ?? null) === 'carrier'
                && (float) ($payment['price'] ?? 0) > 0) {
                return $payment;
            }
        }

        return null;
    }
}
