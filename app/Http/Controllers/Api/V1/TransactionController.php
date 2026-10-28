<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Transaction;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

class TransactionController extends ApiController
{
    public function index(Request $request)
    {
        $data = $request->validate([
            'account_id' => 'nullable|string',
            'status' => 'nullable|in:pending,succeeded,failed,reversed',
            'type' => 'nullable|in:payment,reversal',
            'reference' => 'nullable|string|max:100',
            'from' => 'nullable|date',
            'to' => 'nullable|date',
            'min_amount' => 'nullable|numeric',
            'max_amount' => 'nullable|numeric',
            'limit' => 'nullable|integer|min:1|max:200',
        ]);

        $page = Transaction::query()
            ->where('merchant_id', $this->merchant($request)->id)
            ->when($data['account_id'] ?? null, fn ($q, $v) => $q->where('payment_account_id', $this->stripPrefix($v, 'acct')))
            ->when($data['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
            ->when($data['type'] ?? null, fn ($q, $v) => $q->where('type', $v))
            ->when($data['reference'] ?? null, fn ($q, $v) => $q->where('merchant_reference', $v))
            ->when($data['from'] ?? null, fn ($q, $v) => $q->where('paid_at', '>=', self::utc($v)))
            ->when($data['to'] ?? null, fn ($q, $v) => $q->where('paid_at', '<=', self::utc($v, true)))
            ->when($data['min_amount'] ?? null, fn ($q, $v) => $q->where('amount', '>=', $v))
            ->when($data['max_amount'] ?? null, fn ($q, $v) => $q->where('amount', '<=', $v))
            ->orderByDesc('paid_at')
            ->orderByDesc('id')
            ->cursorPaginate($data['limit'] ?? 50)
            ->withQueryString();

        return response()->json([
            'object' => 'list',
            'data' => collect($page->items())->map->toApiArray(),
            'next_cursor' => $page->nextCursor()?->encode(),
            'prev_cursor' => $page->previousCursor()?->encode(),
            'has_more' => $page->hasMorePages(),
        ]);
    }

    /** Interpret dates without an offset as Nairobi time; a bare date for "to" means end of that day. */
    protected static function utc(string $value, bool $endOfDay = false): CarbonImmutable
    {
        $d = CarbonImmutable::parse($value, 'Africa/Nairobi');
        if ($endOfDay && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            $d = $d->endOfDay();
        }

        return $d->utc();
    }

    public function show(Request $request, string $id)
    {
        return $this->respond($request, fn ($q) => $q->whereKey($this->stripPrefix($id, 'txn')));
    }

    public function byReference(Request $request, string $reference)
    {
        $txns = Transaction::query()
            ->where('merchant_id', $this->merchant($request)->id)
            ->where('merchant_reference', $reference)
            ->orderByDesc('paid_at')
            ->get();

        return $txns->isEmpty()
            ? $this->notFound('Transaction')
            : response()->json(['object' => 'list', 'data' => $txns->map->toApiArray()]);
    }

    public function byReceipt(Request $request, string $receipt)
    {
        return $this->respond($request, fn ($q) => $q->where('provider_transaction_id', strtoupper($receipt))->where('type', 'payment'));
    }

    protected function respond(Request $request, callable $scope)
    {
        $q = Transaction::query()->where('merchant_id', $this->merchant($request)->id);
        $txn = $scope($q)->first();

        return $txn ? response()->json($txn->toApiArray()) : $this->notFound('Transaction');
    }
}
