<?php

$title = 'Products';
$currentUser = session_status() === PHP_SESSION_ACTIVE
    ? ($_SESSION['user'] ?? null)
    : null;
if (!is_array($currentUser)) {
    $currentUser = null;
}
$csrfToken = is_string($csrfToken ?? null) ? $csrfToken : '';

$escape = static fn (string $value): string => htmlspecialchars(
    $value,
    ENT_QUOTES | ENT_SUBSTITUTE,
    'UTF-8'
);
$pageUrl = static fn (int $number): string => '?' . http_build_query([
    'page' => $number,
    'per_page' => $perPage,
    'sort_by' => $sortBy,
    'sort_direction' => $sortDirection,
]);

ob_start();
?>
<div class="space-y-8">
    <section class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
        <div>
            <p class="text-sm font-semibold uppercase tracking-wider text-blue-600">Vending machine</p>
            <h1 class="mt-2 text-3xl font-extrabold tracking-tight text-slate-900 sm:text-4xl">Our Products</h1>
            <p class="mt-2 text-slate-500">Explore our available products and make your purchase.</p>
        </div>

        <?php if (($currentUser['role'] ?? null) === 'admin'): ?>
            <a href="<?= $escape($basePath . '/admin/products/create') ?>"
               class="inline-flex items-center justify-center gap-2 rounded-xl bg-blue-600 px-5 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                <span class="text-lg" aria-hidden="true">+</span>
                Add Product
            </a>
        <?php endif; ?>
    </section>

    <?php if (is_string($successMessage ?? null)): ?>
        <div class="flex items-center gap-3 rounded-xl border border-green-200 bg-green-50 p-4 text-green-800"
             role="status">
            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-green-100 font-bold"
                  aria-hidden="true">&#10003;</span>
            <p class="text-sm font-medium"><?= $escape($successMessage) ?></p>
        </div>
    <?php endif; ?>

    <?php if (is_string($errorMessage ?? null)): ?>
        <div class="flex items-center gap-3 rounded-xl border border-red-200 bg-red-50 p-4 text-red-800"
             role="alert">
            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-red-100 font-bold"
                  aria-hidden="true">!</span>
            <p class="text-sm font-medium"><?= $escape($errorMessage) ?></p>
        </div>
    <?php endif; ?>

    <section class="grid grid-cols-1 gap-4 sm:grid-cols-2" aria-label="Product summary">
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-sm font-medium text-slate-500">Total Products</p>
            <p class="mt-2 text-3xl font-bold text-slate-900"><?= (int) $totalProducts ?></p>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-sm font-medium text-slate-500">Current Page</p>
            <p class="mt-2 text-3xl font-bold text-slate-900">
                <?= (int) $page ?> <span class="text-lg font-medium text-slate-400">/ <?= (int) $totalPages ?></span>
            </p>
        </div>
    </section>

    <section aria-labelledby="available-products-heading">
        <div class="mb-5 flex flex-col justify-between gap-3 sm:flex-row sm:items-center">
            <div>
                <h2 id="available-products-heading" class="text-xl font-bold text-slate-900">Available Products</h2>
                <?php $pageProductCount = count($products); ?>
                <p class="mt-1 text-sm text-slate-500">
                    Showing <?= $pageProductCount ?> product<?= $pageProductCount === 1 ? '' : 's' ?> on this page.
                </p>
            </div>

            <form method="get" class="flex flex-wrap items-center gap-2">
                <input type="hidden" name="page" value="1">
                <input type="hidden" name="per_page" value="<?= (int) $perPage ?>">
                <label for="sort_by" class="text-sm font-medium text-slate-600">Sort by</label>
                <select id="sort_by"
                        name="sort_by"
                        class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-700 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100">
                    <?php foreach ([
                        'id' => 'Product ID',
                        'name' => 'Name',
                        'price' => 'Price',
                        'quantity_available' => 'Stock',
                        'created_at' => 'Date created',
                        'updated_at' => 'Recently updated',
                    ] as $value => $label): ?>
                        <option value="<?= $escape($value) ?>" <?= $sortBy === $value ? 'selected' : '' ?>>
                            <?= $escape($label) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <select name="sort_direction"
                        aria-label="Sort direction"
                        class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-700 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100">
                    <option value="ASC" <?= strtoupper($sortDirection) === 'ASC' ? 'selected' : '' ?>>Ascending</option>
                    <option value="DESC" <?= strtoupper($sortDirection) === 'DESC' ? 'selected' : '' ?>>Descending</option>
                </select>

                <button type="submit"
                        class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white transition hover:bg-slate-700">
                    Apply
                </button>
            </form>
        </div>

        <?php if ($products === []): ?>
            <div class="rounded-2xl border border-dashed border-slate-300 bg-white px-6 py-16 text-center">
                <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-2xl bg-slate-100 text-3xl"
                     aria-hidden="true">📦</div>
                <h3 class="mt-5 text-lg font-bold text-slate-900">No products available</h3>
                <p class="mt-2 text-sm text-slate-500">There are currently no products to display.</p>
                <?php if (($currentUser['role'] ?? null) === 'admin'): ?>
                    <a href="<?= $escape($basePath . '/admin/products/create') ?>"
                       class="mt-5 inline-flex rounded-lg bg-blue-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-blue-700">
                        Add your first product
                    </a>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-3">
                <?php foreach ($products as $product): ?>
                    <?php
                    $productId = (int) $product['id'];
                    $productName = (string) $product['name'];
                    $stock = (int) $product['quantity_available'];
                    $price = (string) $product['price'];
                    $inStock = $stock > 0;
                    ?>
                    <article class="group overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm transition duration-200 hover:-translate-y-1 hover:border-blue-200 hover:shadow-lg">
                        <div class="relative flex h-44 items-center justify-center overflow-hidden bg-gradient-to-br from-blue-50 via-indigo-50 to-slate-100">
                            <div class="absolute -right-8 -top-8 h-32 w-32 rounded-full bg-white/40"></div>
                            <div class="absolute -bottom-12 -left-8 h-36 w-36 rounded-full bg-blue-100/50"></div>
                            <div class="relative flex h-24 w-24 items-center justify-center rounded-3xl border border-white/70 bg-white/70 text-5xl shadow-sm backdrop-blur transition duration-200 group-hover:scale-110"
                                 aria-hidden="true">🥤</div>
                            <span class="absolute right-4 top-4 rounded-full px-3 py-1 text-xs font-bold <?= $inStock ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' ?>">
                                <?= $inStock ? 'In Stock' : 'Out of Stock' ?>
                            </span>
                        </div>

                        <div class="p-5">
                            <h3 class="truncate text-lg font-bold text-slate-900"><?= $escape($productName) ?></h3>
                            <p class="mt-1 text-xs font-medium uppercase tracking-wider text-slate-400">
                                Product #<?= $productId ?>
                            </p>
                            <div class="mt-5 flex items-end justify-between border-t border-slate-100 pt-4">
                                <div>
                                    <p class="text-xs font-medium text-slate-500">Price</p>
                                    <p class="mt-1 text-2xl font-extrabold text-blue-600"><?= $escape($price) ?></p>
                                </div>
                                <div class="text-right">
                                    <p class="text-xs font-medium text-slate-500">Available</p>
                                    <p class="mt-1 text-lg font-bold <?= $inStock ? 'text-slate-800' : 'text-red-600' ?>">
                                        <?= $stock ?> units
                                    </p>
                                </div>
                            </div>
                            <a href="<?= $escape($basePath . '/products/' . $productId) ?>"
                               class="mt-5 flex w-full items-center justify-center gap-2 rounded-xl bg-slate-900 px-4 py-3 text-sm font-semibold text-white transition hover:bg-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                                View Product <span aria-hidden="true">&rarr;</span>
                            </a>
                            <?php if (($currentUser['role'] ?? null) === 'admin'): ?>
                                <div class="mt-3 grid grid-cols-2 gap-3">
                                    <a href="<?= $escape($basePath . '/admin/products/' . $productId . '/edit') ?>"
                                       class="flex items-center justify-center rounded-xl border border-blue-200 px-4 py-2.5 text-sm font-semibold text-blue-700 transition hover:bg-blue-50">
                                        Edit
                                    </a>
                                    <form action="<?= $escape($basePath . '/admin/products/' . $productId . '/delete') ?>"
                                          method="post"
                                          onsubmit="return confirm('Delete this product? Products with transaction history cannot be deleted.')">
                                        <input type="hidden" name="_csrf_token" value="<?= $escape($csrfToken) ?>">
                                        <button type="submit"
                                                class="w-full rounded-xl border border-red-200 px-4 py-2.5 text-sm font-semibold text-red-700 transition hover:bg-red-50">
                                            Delete
                                        </button>
                                    </form>
                                </div>
                            <?php endif; ?>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <?php if ($totalPages > 1): ?>
        <nav class="flex flex-col items-center justify-between gap-4 rounded-2xl border border-slate-200 bg-white p-4 sm:flex-row"
             aria-label="Product pages">
            <p class="text-sm text-slate-500">
                Page <span class="font-bold text-slate-900"><?= (int) $page ?></span>
                of <span class="font-bold text-slate-900"><?= (int) $totalPages ?></span>
                &middot; <?= (int) $totalProducts ?> total products
            </p>
            <div class="flex items-center gap-2">
                <?php if ($page > 1): ?>
                    <a href="<?= $escape($pageUrl($page - 1)) ?>"
                       class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-100">
                        &larr; Previous
                    </a>
                <?php else: ?>
                    <span class="cursor-not-allowed rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-300">
                        &larr; Previous
                    </span>
                <?php endif; ?>

                <?php if ($page < $totalPages): ?>
                    <a href="<?= $escape($pageUrl($page + 1)) ?>"
                       class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-blue-700">
                        Next &rarr;
                    </a>
                <?php else: ?>
                    <span class="cursor-not-allowed rounded-lg bg-slate-100 px-4 py-2 text-sm font-semibold text-slate-400">
                        Next &rarr;
                    </span>
                <?php endif; ?>
            </div>
        </nav>
    <?php endif; ?>
</div>
<?php

$content = ob_get_clean();
require dirname(__DIR__) . '/layouts/app.php';
