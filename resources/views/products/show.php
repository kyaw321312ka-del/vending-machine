<?php

$errors = is_array($errors ?? null) ? $errors : [];
$old = is_array($old ?? null) ? $old : [];
$currentUser = is_array($currentUser ?? null) ? $currentUser : null;
$csrfToken = is_string($csrfToken ?? null) ? $csrfToken : '';
$assetUrl = is_string($assetUrl ?? null) ? $assetUrl : '/assets/css/output.css';
$basePath = is_string($basePath ?? null) ? $basePath : '';
$productsUrl = $basePath . '/products';
$historyUrl = $basePath . '/transactions';
$purchaseUrl = $basePath . '/products/' . (int) ($product['id'] ?? 0) . '/purchase';
$escape = static fn (string $value): string => htmlspecialchars(
    $value,
    ENT_QUOTES | ENT_SUBSTITUTE,
    'UTF-8'
);
?>
<?php

$title = (string) $product['name'];
ob_start();
?>
    <main class="mx-auto max-w-2xl px-6 py-10">
        <a class="text-blue-700 underline" href="<?= $escape($productsUrl) ?>">All products</a>
        <h1 class="my-6 text-3xl font-bold"><?= $escape((string) $product['name']) ?></h1>

        <?php if (is_string($successMessage ?? null)): ?>
            <p class="mb-4 rounded bg-green-100 p-4 text-green-800" role="status"><?= $escape($successMessage) ?></p>
        <?php endif; ?>
        <?php if (is_string($errorMessage ?? null)): ?>
            <p class="mb-4 rounded bg-red-100 p-4 text-red-800" role="alert"><?= $escape($errorMessage) ?></p>
        <?php endif; ?>

        <dl class="space-y-3 rounded bg-white p-6 shadow">
            <div class="flex justify-between gap-4">
                <dt class="font-medium">Price</dt>
                <dd><?= $escape((string) $product['price']) ?></dd>
            </div>
            <div class="flex justify-between gap-4">
                <dt class="font-medium">Available</dt>
                <dd><?= (int) $product['quantity_available'] ?></dd>
            </div>
        </dl>

        <?php if ($currentUser !== null): ?>
            <?php if (isset($errors['quantity']) && is_string($errors['quantity'])): ?>
                <p class="mt-4 rounded bg-red-100 p-4 text-red-800" role="alert"><?= $escape($errors['quantity']) ?></p>
            <?php endif; ?>

            <?php if ((int) $product['quantity_available'] > 0): ?>
                <form action="<?= $escape($purchaseUrl) ?>" method="post" class="mt-6 space-y-4 rounded bg-white p-6 shadow">
                    <input type="hidden" name="_csrf_token" value="<?= $escape($csrfToken) ?>">
                    <div>
                        <label for="quantity" class="mb-1 block font-medium">Quantity</label>
                        <input
                            id="quantity"
                            name="quantity"
                            type="number"
                            min="1"
                            max="<?= (int) $product['quantity_available'] ?>"
                            step="1"
                            required
                            value="<?= $escape(is_string($old['quantity'] ?? null) ? $old['quantity'] : '1') ?>"
                            class="w-full rounded border border-slate-300 px-3 py-2"
                            aria-invalid="<?= isset($errors['quantity']) ? 'true' : 'false' ?>"
                        >
                    </div>
                    <button type="submit" class="rounded bg-blue-700 px-4 py-2 font-medium text-white">Purchase</button>
                </form>
            <?php else: ?>
                <p class="mt-6 rounded bg-white p-4 shadow">This product is currently out of stock.</p>
            <?php endif; ?>

            <p class="mt-4"><a class="text-blue-700 underline" href="<?= $escape($historyUrl) ?>">View purchase history</a></p>
        <?php else: ?>
            <p class="mt-6 rounded bg-white p-4 shadow">Log in to purchase this product.</p>
        <?php endif; ?>
    </main>
<?php

$content = ob_get_clean();
require dirname(__DIR__) . '/layouts/app.php';
