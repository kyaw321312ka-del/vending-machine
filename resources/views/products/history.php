<?php

$basePath = is_string($basePath ?? null) ? $basePath : '';
$productsUrl = $basePath . '/products';
$currentUser = session_status() === PHP_SESSION_ACTIVE ? ($_SESSION['user'] ?? null) : null;
if (!is_array($currentUser)) {
    $currentUser = null;
}
$isAdmin = ($currentUser['role'] ?? null) === 'admin';
$historyTitle = $isAdmin ? 'All Transactions' : 'Purchase History';
$emptyMessage = $isAdmin
    ? 'There are no transactions yet.'
    : 'You have no purchases yet.';
$escape = static fn (string $value): string => htmlspecialchars(
    $value,
    ENT_QUOTES | ENT_SUBSTITUTE,
    'UTF-8'
);
$pageUrl = static fn (int $number): string => $basePath . '/transactions?' . http_build_query([
    'page' => $number,
    'per_page' => $perPage,
]);
?>
<?php

$title = $historyTitle;
$assetUrl = $basePath . '/assets/css/output.css';
ob_start();
?>
    <main class="mx-auto max-w-5xl px-6 py-10">
        <a class="text-blue-700 underline" href="<?= $escape($productsUrl) ?>">All products</a>
        <h1 class="my-6 text-3xl font-bold"><?= $escape($historyTitle) ?></h1>

        <?php if (is_string($successMessage ?? null)): ?>
            <p class="mb-4 rounded bg-green-100 p-4 text-green-800" role="status"><?= $escape($successMessage) ?></p>
        <?php endif; ?>
        <?php if (is_string($errorMessage ?? null)): ?>
            <p class="mb-4 rounded bg-red-100 p-4 text-red-800" role="alert"><?= $escape($errorMessage) ?></p>
        <?php endif; ?>

        <?php if ($transactions === []): ?>
            <p class="rounded bg-white p-6 shadow"><?= $escape($emptyMessage) ?></p>
        <?php else: ?>
            <div class="overflow-x-auto rounded bg-white shadow">
                <table class="w-full border-collapse text-left">
                    <thead class="bg-slate-200">
                        <tr>
                            <th class="p-3">Date</th>
                            <?php if ($isAdmin): ?>
                                <th class="p-3">Customer</th>
                            <?php endif; ?>
                            <th class="p-3">Product</th>
                            <th class="p-3">Quantity</th>
                            <th class="p-3">Unit price</th>
                            <th class="p-3">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($transactions as $transaction): ?>
                            <tr class="border-t border-slate-200">
                                <td class="p-3"><?= $escape((string) $transaction['created_at']) ?></td>
                                <?php if ($isAdmin): ?>
                                    <td class="p-3"><?= $escape((string) $transaction['user_name']) ?></td>
                                <?php endif; ?>
                                <td class="p-3"><?= $escape((string) $transaction['product_name']) ?></td>
                                <td class="p-3"><?= (int) $transaction['quantity'] ?></td>
                                <td class="p-3"><?= $escape((string) $transaction['unit_price']) ?></td>
                                <td class="p-3"><?= $escape((string) $transaction['total_price']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <nav class="mt-6 flex items-center justify-between" aria-label="Purchase history pages">
            <?php if ($page > 1): ?>
                <a class="rounded bg-white px-4 py-2 shadow" href="<?= $escape($pageUrl($page - 1)) ?>">Previous</a>
            <?php else: ?>
                <span></span>
            <?php endif; ?>

            <span>
                Page <?= (int) $page ?> of <?= (int) $totalPages ?>
                (<?= (int) $totalTransactions ?> <?= $isAdmin ? 'transactions' : 'purchases' ?>)
            </span>

            <?php if ($page < $totalPages): ?>
                <a class="rounded bg-white px-4 py-2 shadow" href="<?= $escape($pageUrl($page + 1)) ?>">Next</a>
            <?php else: ?>
                <span></span>
            <?php endif; ?>
        </nav>
    </main>
<?php

$content = ob_get_clean();
require dirname(__DIR__) . '/layouts/app.php';
