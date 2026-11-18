<?php

$errors = is_array($errors ?? null) ? $errors : [];
$old = is_array($old ?? null) ? $old : [];
$csrfToken = is_string($csrfToken ?? null) ? $csrfToken : '';
$assetUrl = is_string($assetUrl ?? null) ? $assetUrl : '/assets/css/output.css';
$basePath = is_string($basePath ?? null) ? $basePath : '';
$productId = (int) ($product['id'] ?? 0);
$formAction = $basePath . '/admin/products/' . $productId . '/update';
$productsUrl = $basePath . '/products';
$escape = static fn (string $value): string => htmlspecialchars(
    $value,
    ENT_QUOTES | ENT_SUBSTITUTE,
    'UTF-8'
);
$value = static fn (string $field): string => is_string($old[$field] ?? null)
    ? $old[$field]
    : (string) ($product[$field] ?? '');
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Edit Product - Vending Machine</title>
    <link rel="stylesheet" href="<?= $escape($assetUrl) ?>">
</head>
<body class="min-h-screen bg-slate-100 text-slate-900">
    <main class="mx-auto max-w-2xl px-6 py-10">
        <h1 class="mb-6 text-3xl font-bold">Edit Product</h1>

        <?php if (isset($errors['_general']) && is_string($errors['_general'])): ?>
            <p class="mb-4 rounded bg-red-100 p-4 text-red-800" role="alert"><?= $escape($errors['_general']) ?></p>
        <?php endif; ?>

        <form action="<?= $escape($formAction) ?>" method="post" class="space-y-5 rounded bg-white p-6 shadow">
            <input type="hidden" name="_csrf_token" value="<?= $escape($csrfToken) ?>">

            <div>
                <label for="name" class="mb-1 block font-medium">Product name</label>
                <input
                    id="name"
                    name="name"
                    type="text"
                    maxlength="150"
                    required
                    value="<?= $escape($value('name')) ?>"
                    class="w-full rounded border border-slate-300 px-3 py-2"
                    aria-invalid="<?= isset($errors['name']) ? 'true' : 'false' ?>"
                    <?= isset($errors['name']) ? 'aria-describedby="name-error"' : '' ?>
                >
                <?php if (isset($errors['name']) && is_string($errors['name'])): ?>
                    <p id="name-error" class="mt-1 text-sm text-red-700"><?= $escape($errors['name']) ?></p>
                <?php endif; ?>
            </div>

            <div>
                <label for="price" class="mb-1 block font-medium">Price</label>
                <input
                    id="price"
                    name="price"
                    type="number"
                    min="0.001"
                    step="0.001"
                    required
                    value="<?= $escape($value('price')) ?>"
                    class="w-full rounded border border-slate-300 px-3 py-2"
                    aria-invalid="<?= isset($errors['price']) ? 'true' : 'false' ?>"
                    <?= isset($errors['price']) ? 'aria-describedby="price-error"' : '' ?>
                >
                <?php if (isset($errors['price']) && is_string($errors['price'])): ?>
                    <p id="price-error" class="mt-1 text-sm text-red-700"><?= $escape($errors['price']) ?></p>
                <?php endif; ?>
            </div>

            <div>
                <label for="quantity_available" class="mb-1 block font-medium">Available quantity</label>
                <input
                    id="quantity_available"
                    name="quantity_available"
                    type="number"
                    min="0"
                    step="1"
                    required
                    value="<?= $escape($value('quantity_available')) ?>"
                    class="w-full rounded border border-slate-300 px-3 py-2"
                    aria-invalid="<?= isset($errors['quantity_available']) ? 'true' : 'false' ?>"
                    <?= isset($errors['quantity_available']) ? 'aria-describedby="quantity-error"' : '' ?>
                >
                <?php if (isset($errors['quantity_available']) && is_string($errors['quantity_available'])): ?>
                    <p id="quantity-error" class="mt-1 text-sm text-red-700"><?= $escape($errors['quantity_available']) ?></p>
                <?php endif; ?>
            </div>

            <div class="flex gap-3">
                <button type="submit" class="rounded bg-blue-700 px-4 py-2 font-medium text-white">Update product</button>
                <a href="<?= $escape($productsUrl) ?>" class="rounded border border-slate-300 px-4 py-2 font-medium">Cancel</a>
            </div>
        </form>
    </main>
</body>
</html>
