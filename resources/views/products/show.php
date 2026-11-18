<?php

$errors = is_array($errors ?? null) ? $errors : [];
$old = is_array($old ?? null) ? $old : [];
$currentUser = is_array($currentUser ?? null) ? $currentUser : null;
$csrfToken = is_string($csrfToken ?? null) ? $csrfToken : '';
$assetUrl = is_string($assetUrl ?? null) ? $assetUrl : '/assets/css/output.css';
$basePath = is_string($basePath ?? null) ? $basePath : '';

$productsUrl = $basePath . '/products';
$historyUrl = $basePath . '/transactions';
$productId = (int) ($product['id'] ?? 0);
$productName = (string) ($product['name'] ?? '');
$productPrice = (string) ($product['price'] ?? '0.000');
$productStock = (int) ($product['quantity_available'] ?? 0);
$isEnabled = (int) ($product['is_enabled'] ?? 0) === 1;
$purchaseUrl = $basePath . '/products/' . $productId . '/purchase';
$inStock = $isEnabled && $productStock > 0;

$escape = static fn (string $value): string => htmlspecialchars(
    $value,
    ENT_QUOTES | ENT_SUBSTITUTE,
    'UTF-8'
);
$title = $productName;

ob_start();
?>
<div class="mx-auto max-w-6xl space-y-8">
    <nav aria-label="Breadcrumb" class="flex items-center gap-2 text-sm">
        <a href="<?= $escape($productsUrl) ?>"
           class="font-medium text-slate-500 transition hover:text-blue-600">
            Products
        </a>
        <span class="text-slate-300" aria-hidden="true">/</span>
        <span class="font-semibold text-slate-900"><?= $escape($productName) ?></span>
    </nav>

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

    <div class="grid grid-cols-1 gap-8 lg:grid-cols-5">
        <section class="space-y-6 lg:col-span-3" aria-labelledby="product-name">
            <div class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
                <div class="relative flex min-h-72 items-center justify-center overflow-hidden bg-gradient-to-br from-blue-100 via-indigo-50 to-slate-100 sm:min-h-96">
                    <div class="absolute -right-16 -top-16 h-64 w-64 rounded-full bg-white/40"></div>
                    <div class="absolute -bottom-24 -left-12 h-72 w-72 rounded-full bg-blue-200/30"></div>
                    <div class="relative flex h-40 w-40 items-center justify-center rounded-[2rem] border border-white/70 bg-white/70 text-8xl shadow-xl backdrop-blur-sm sm:h-52 sm:w-52 sm:text-9xl"
                         aria-hidden="true">🥤</div>
                    <span class="absolute left-5 top-5 rounded-full px-4 py-2 text-xs font-bold <?= $inStock ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' ?>">
                        <?= $inStock ? 'Available' : (!$isEnabled ? 'Disabled' : 'Out of Stock') ?>
                    </span>
                </div>

                <div class="space-y-5 p-6 sm:p-8">
                    <div>
                        <p class="text-sm font-semibold uppercase tracking-wider text-blue-600">Product details</p>
                        <h1 id="product-name" class="mt-2 text-3xl font-extrabold tracking-tight text-slate-900 sm:text-4xl">
                            <?= $escape($productName) ?>
                        </h1>
                        <p class="mt-2 text-sm text-slate-500">Product ID: #<?= $productId ?></p>
                    </div>

                    <div class="border-t border-slate-100 pt-5">
                        <p class="text-sm font-medium text-slate-500">Product price</p>
                        <p class="mt-2 text-4xl font-extrabold text-blue-600"><?= $escape($productPrice) ?></p>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div class="rounded-xl bg-slate-50 p-4">
                            <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">Stock status</p>
                            <p class="mt-2 font-bold <?= $inStock ? 'text-green-600' : 'text-red-600' ?>">
                                <?= $inStock ? 'In Stock' : (!$isEnabled ? 'Disabled' : 'Out of Stock') ?>
                            </p>
                        </div>
                        <div class="rounded-xl bg-slate-50 p-4">
                            <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">Available quantity</p>
                            <p class="mt-2 font-bold text-slate-900"><?= $productStock ?> units</p>
                        </div>
                    </div>
                </div>
            </div>

            <a href="<?= $escape($productsUrl) ?>"
               class="inline-flex items-center gap-2 text-sm font-semibold text-slate-600 transition hover:text-blue-600">
                <span aria-hidden="true">&larr;</span>
                Back to all products
            </a>
        </section>

        <aside class="space-y-5 lg:col-span-2" aria-labelledby="checkout-heading">
            <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
                <p class="text-sm font-semibold uppercase tracking-wider text-blue-600">Checkout</p>
                <h2 id="checkout-heading" class="mt-2 text-2xl font-extrabold text-slate-900">
                    Purchase this product
                </h2>
                <p class="mt-2 text-sm leading-6 text-slate-500">Select the quantity you want to purchase.</p>
                <div class="my-6 border-t border-slate-100"></div>

                <?php if ($currentUser !== null && ($currentUser['role'] ?? null) === 'user'): ?>
                    <?php if (isset($errors['quantity']) && is_string($errors['quantity'])): ?>
                        <div id="quantity-error"
                             class="mb-5 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-700"
                             role="alert">
                            <?= $escape($errors['quantity']) ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($inStock): ?>
                        <form action="<?= $escape($purchaseUrl) ?>" method="post" class="space-y-5">
                            <input type="hidden" name="_csrf_token" value="<?= $escape($csrfToken) ?>">

                            <div>
                                <label for="quantity" class="mb-2 block text-sm font-semibold text-slate-700">
                                    Quantity
                                </label>
                                <input
                                    id="quantity"
                                    name="quantity"
                                    type="number"
                                    min="1"
                                    max="<?= $productStock ?>"
                                    step="1"
                                    required
                                    value="<?= $escape(is_string($old['quantity'] ?? null) ? $old['quantity'] : '1') ?>"
                                    class="w-full rounded-xl border border-slate-300 bg-slate-50 px-4 py-3 text-lg font-semibold text-slate-900 outline-none transition focus:border-blue-500 focus:bg-white focus:ring-4 focus:ring-blue-100"
                                    aria-invalid="<?= isset($errors['quantity']) ? 'true' : 'false' ?>"
                                    aria-describedby="quantity-help<?= isset($errors['quantity']) ? ' quantity-error' : '' ?>"
                                >
                                <p id="quantity-help" class="mt-2 text-xs text-slate-500">
                                    Maximum available: <?= $productStock ?> units
                                </p>
                            </div>

                            <div class="rounded-xl bg-blue-50 p-4">
                                <div class="flex items-center justify-between gap-3">
                                    <span class="text-sm font-medium text-blue-800">Unit price</span>
                                    <span class="font-bold text-blue-900"><?= $escape($productPrice) ?></span>
                                </div>
                                <p class="mt-2 text-xs leading-5 text-blue-700">
                                    Your final total will be calculated securely by the server.
                                </p>
                            </div>

                            <button type="submit"
                                    class="flex w-full items-center justify-center gap-2 rounded-xl bg-blue-600 px-5 py-4 text-sm font-bold text-white shadow-sm transition hover:bg-blue-700 focus:outline-none focus:ring-4 focus:ring-blue-200">
                                <span aria-hidden="true">🛒</span>
                                Purchase Now
                            </button>
                        </form>
                    <?php else: ?>
                        <div class="rounded-xl border border-red-200 bg-red-50 p-5 text-center">
                            <div class="text-3xl" aria-hidden="true">📦</div>
                            <h3 class="mt-3 font-bold text-red-700">Currently Out of Stock</h3>
                            <p class="mt-2 text-sm text-red-600">This product is currently unavailable.</p>
                        </div>
                    <?php endif; ?>

                    <a href="<?= $escape($historyUrl) ?>"
                       class="flex items-center justify-center gap-2 rounded-xl border border-slate-200 px-5 py-3 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">
                        View Purchase History
                        <span aria-hidden="true">&rarr;</span>
                    </a>
                <?php elseif ($currentUser !== null && ($currentUser['role'] ?? null) === 'admin'): ?>
                    <div class="rounded-xl border border-blue-200 bg-blue-50 p-5">
                        <div class="text-3xl" aria-hidden="true">🛠️</div>
                        <h3 class="mt-3 font-bold text-blue-900">Administrator Account</h3>
                        <p class="mt-2 text-sm leading-6 text-blue-800">
                            Administrators manage products and inventory. Please use a customer account to purchase products.
                        </p>
                        <a href="<?= $escape($basePath . '/admin/products/' . $productId . '/edit') ?>"
                           class="mt-5 flex w-full items-center justify-center rounded-xl bg-blue-600 px-5 py-3 text-sm font-bold text-white transition hover:bg-blue-700">
                            Edit Product Availability
                        </a>
                    </div>
                    <a href="<?= $escape($historyUrl) ?>"
                       class="flex items-center justify-center gap-2 rounded-xl border border-slate-200 px-5 py-3 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">
                        View All Transactions
                        <span aria-hidden="true">&rarr;</span>
                    </a>
                <?php else: ?>
                    <div class="rounded-xl border border-amber-200 bg-amber-50 p-5">
                        <div class="text-3xl" aria-hidden="true">🔒</div>
                        <h3 class="mt-3 font-bold text-amber-900">Sign in required</h3>
                        <p class="mt-2 text-sm leading-6 text-amber-800">
                            Please sign in to your account before purchasing this product.
                        </p>
                        <a href="<?= $escape($basePath . '/auth/login') ?>"
                           class="mt-5 flex w-full items-center justify-center rounded-xl bg-blue-600 px-5 py-3 text-sm font-bold text-white transition hover:bg-blue-700">
                            Sign In to Continue
                        </a>
                        <a href="<?= $escape($basePath . '/auth/register') ?>"
                           class="mt-3 flex w-full items-center justify-center rounded-xl border border-amber-300 px-5 py-3 text-sm font-semibold text-amber-900 transition hover:bg-amber-100">
                            Create Account
                        </a>
                    </div>
                <?php endif; ?>
            </div>

            <div class="rounded-2xl border border-slate-200 bg-white p-5">
                <div class="flex items-start gap-3">
                    <span class="text-xl" aria-hidden="true">🛡️</span>
                    <div>
                        <h3 class="text-sm font-bold text-slate-900">Secure Purchase</h3>
                        <p class="mt-1 text-xs leading-5 text-slate-500">
                            Stock updates and transaction records are handled securely by the server.
                        </p>
                    </div>
                </div>
            </div>
        </aside>
    </div>
</div>
<?php

$content = ob_get_clean();
require dirname(__DIR__) . '/layouts/app.php';
