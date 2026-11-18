<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Products - Vending Machine</title>
    <link rel="stylesheet" href="<?= htmlspecialchars($assetUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
</head>
<body class="min-h-screen bg-slate-100 text-slate-900">
    <main class="mx-auto max-w-5xl px-6 py-10">
        <h1 class="mb-6 text-3xl font-bold">Products</h1>

        <?php if (is_string($successMessage ?? null)): ?>
            <p class="mb-4 rounded bg-green-100 p-4 text-green-800" role="status">
                <?= htmlspecialchars($successMessage, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
            </p>
        <?php endif; ?>

        <?php if (is_string($errorMessage ?? null)): ?>
            <p class="mb-4 rounded bg-red-100 p-4 text-red-800" role="alert">
                <?= htmlspecialchars($errorMessage, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
            </p>
        <?php endif; ?>

        <?php if ($products === []): ?>
            <p class="rounded bg-white p-6 shadow">No products are available.</p>
        <?php else: ?>
            <div class="overflow-x-auto rounded bg-white shadow">
                <table class="w-full border-collapse text-left">
                    <thead class="bg-slate-200">
                        <tr>
                            <th class="p-3">Name</th>
                            <th class="p-3">Price</th>
                            <th class="p-3">Available</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($products as $product): ?>
                            <tr class="border-t border-slate-200">
                                <td class="p-3">
                                    <a class="text-blue-700 underline" href="<?= htmlspecialchars($basePath . '/products/' . (int) $product['id'], ENT_QUOTES, 'UTF-8') ?>">
                                        <?= htmlspecialchars((string) $product['name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
                                    </a>
                                </td>
                                <td class="p-3">
                                    <?= htmlspecialchars((string) $product['price'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
                                </td>
                                <td class="p-3">
                                    <?= (int) $product['quantity_available'] ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <nav class="mt-6 flex items-center justify-between" aria-label="Product pages">
            <?php
            $pageUrl = static fn (int $number): string => '?' . http_build_query([
                'page' => $number,
                'per_page' => $perPage,
                'sort_by' => $sortBy,
                'sort_direction' => $sortDirection,
            ]);
            ?>
            <?php if ($page > 1): ?>
                <a class="rounded bg-white px-4 py-2 shadow" href="<?= htmlspecialchars($pageUrl($page - 1), ENT_QUOTES, 'UTF-8') ?>">Previous</a>
            <?php else: ?>
                <span></span>
            <?php endif; ?>

            <span>Page <?= $page ?> of <?= $totalPages ?> (<?= $totalProducts ?> products)</span>

            <?php if ($page < $totalPages): ?>
                <a class="rounded bg-white px-4 py-2 shadow" href="<?= htmlspecialchars($pageUrl($page + 1), ENT_QUOTES, 'UTF-8') ?>">Next</a>
            <?php else: ?>
                <span></span>
            <?php endif; ?>
        </nav>
    </main>
</body>
</html>
